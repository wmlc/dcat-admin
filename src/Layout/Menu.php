<?php

namespace Dcat\Admin\Layout;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

class Menu
{
    protected static $helperNodes = [
        [
            'id'        => 1,
            'title'     => 'Helpers',
            'icon'      => 'fa fa-keyboard-o',
            'uri'       => '',
            'parent_id' => 0,
        ],
        [
            'id'        => 2,
            'title'     => 'Extensions',
            'icon'      => '',
            'uri'       => 'auth/extensions',
            'parent_id' => 1,
        ],
        [
            'id'        => 3,
            'title'     => 'Scaffold',
            'icon'      => '',
            'uri'       => 'helpers/scaffold',
            'parent_id' => 1,
        ],
        [
            'id'        => 4,
            'title'     => 'Icons',
            'icon'      => '',
            'uri'       => 'helpers/icons',
            'parent_id' => 1,
        ],
    ];

    protected $view = 'admin::partials.menu';

    /**
     * 当前请求路径命中的菜单 uri，false 表示尚未解析.
     *
     * @var bool|string|null
     */
    protected $resolvedMatch = false;

    public function register()
    {
        if (! admin_has_default_section(Admin::SECTION['LEFT_SIDEBAR_MENU'])) {
            admin_inject_default_section(Admin::SECTION['LEFT_SIDEBAR_MENU'], function () {
                $menuModel = config('admin.database.menu_model');

                return $this->toHtml((new $menuModel())->allNodes()->toArray());
            });
        }

        if (config('app.debug') && config('admin.helpers.enable', true)) {
            $this->add(static::$helperNodes, 20);
        }
    }

    /**
     * 增加菜单节点.
     *
     * @param  array  $nodes
     * @param  int  $priority
     * @return void
     */
    public function add(array $nodes = [], int $priority = 10)
    {
        admin_inject_section(Admin::SECTION['LEFT_SIDEBAR_MENU_BOTTOM'], function () use (&$nodes) {
            return $this->toHtml($nodes);
        }, true, $priority);
    }

    /**
     * 转化为HTML.
     *
     * @param  array  $nodes
     * @return string
     *
     * @throws \Throwable
     */
    public function toHtml($nodes)
    {
        $html = '';

        foreach (Helper::buildNestedArray($nodes) as $item) {
            $html .= $this->render($item);
        }

        return $html;
    }

    /**
     * 设置菜单视图.
     *
     * @param  string  $view
     * @return $this
     */
    public function view(string $view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * 渲染视图.
     *
     * @param  array  $item
     * @return string
     */
    public function render($item)
    {
        return view($this->view, ['item' => &$item, 'builder' => $this])->render();
    }

    /**
     * 判断是否选中.
     *
     * @param  array  $item
     * @param  null|string  $path
     * @return bool
     */
    public function isActive($item, ?string $path = null)
    {
        if (empty($path)) {
            $path = request()->path();
        }

        if (empty($item['children'])) {
            return $this->uriIsActive($item['uri'] ?? null, $path);
        }

        foreach ($item['children'] as $v) {
            if ($this->uriIsActive($v['uri'] ?? null, $path)) {
                return true;
            }
            if (! empty($v['children'])) {
                if ($this->isActive($v, $path)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 判断菜单 uri 是否命中当前请求路径.
     *
     * 除精确匹配外，还支持按路径段的最长前缀匹配，
     * 使资源路由的详情、编辑等子页面（如 orders/12/edit）也能定位到所属菜单.
     *
     * @param  string|null  $uri
     * @param  string  $path
     * @return bool
     */
    protected function uriIsActive($uri, string $path)
    {
        if (empty($uri)) {
            return false;
        }

        $menuPath = trim($this->getPath($uri), '/');

        if ($menuPath === '') {
            return false;
        }

        if ($menuPath == trim($path, '/')) {
            return true;
        }

        // 当前路径存在精确命中的菜单时，前缀匹配让位，避免多个菜单同时高亮
        if (($matched = $this->matchedUri($path)) === null || trim($this->getPath($matched), '/') !== $menuPath) {
            return false;
        }

        return Str::startsWith(trim($path, '/'), $menuPath.'/');
    }

    /**
     * 解析与当前请求路径前缀匹配的最长菜单 uri.
     *
     * @param  string  $path
     * @return string|null
     */
    public function matchedUri(string $path)
    {
        if ($this->resolvedMatch !== false) {
            return $this->resolvedMatch;
        }

        $path = trim($path, '/');

        $best = null;
        $bestLength = -1;

        foreach ($this->menuUris() as $uri) {
            if (empty($uri) || Str::startsWith($uri, ['http://', 'https://'])) {
                continue;
            }

            $menuPath = trim($this->getPath($uri), '/');

            if ($menuPath === '') {
                continue;
            }

            if ($menuPath === $path) {
                $best = $uri;
                break;
            }

            if (Str::startsWith($path, $menuPath.'/') && \strlen($menuPath) > $bestLength) {
                $best = $uri;
                $bestLength = \strlen($menuPath);
            }
        }

        return $this->resolvedMatch = $best;
    }

    /**
     * 获取全部菜单 uri.
     *
     * @return array
     */
    protected function menuUris()
    {
        $model = config('admin.database.menu_model');

        return (new $model)->newQuery()->pluck('uri')->all();
    }

    /**
     * 判断节点是否可见.
     *
     * @param  array  $item
     * @return bool
     */
    public function visible($item)
    {
        if (
            ! $this->checkPermission($item)
            || ! $this->checkExtension($item)
            || ! $this->userCanSeeMenu($item)
        ) {
            return false;
        }

        $show = $item['show'] ?? null;
        if ($show !== null && ! $show) {
            return false;
        }

        return true;
    }

    /**
     * 判断扩展是否启用.
     *
     * @param $item
     * @return bool
     */
    protected function checkExtension($item)
    {
        $extension = $item['extension'] ?? null;

        if (! $extension) {
            return true;
        }

        if (! $extension = Admin::extension($extension)) {
            return false;
        }

        return $extension->enabled();
    }

    /**
     * 判断用户.
     *
     * @param  array|\Dcat\Admin\Models\Menu  $item
     * @return bool
     */
    protected function userCanSeeMenu($item)
    {
        $user = Admin::user();

        if (! $user || ! method_exists($user, 'canSeeMenu')) {
            return true;
        }

        return $user->canSeeMenu($item);
    }

    /**
     * 判断权限.
     *
     * @param $item
     * @return bool
     */
    protected function checkPermission($item)
    {
        $permissionIds = $item['permission_id'] ?? null;
        $roles = array_column(Helper::array($item['roles'] ?? []), 'slug');
        $permissions = array_column(Helper::array($item['permissions'] ?? []), 'slug');

        if (! $permissionIds && ! $roles && ! $permissions) {
            return true;
        }

        $user = Admin::user();

        if (! $user || $user->visible($roles)) {
            return true;
        }

        foreach (array_merge(Helper::array($permissionIds), $permissions) as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  string  $text
     * @return string
     */
    public function translate($text)
    {
        $titleTranslation = 'menu.titles.'.trim(str_replace(' ', '_', strtolower($text)));

        if (Lang::has($titleTranslation)) {
            return __($titleTranslation);
        }

        return $text;
    }

    /**
     * @param  string  $uri
     * @return string
     */
    public function getPath($uri)
    {
        return $uri
            ? (url()->isValidUrl($uri) ? $uri : admin_base_path($uri))
            : $uri;
    }

    /**
     * @param  string  $uri
     * @return string
     */
    public function getUrl($uri)
    {
        return $uri ? admin_url($uri) : $uri;
    }
}
