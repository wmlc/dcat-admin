/*!
 * editormd图片粘贴上传插件
 *
 * @file   image-handle-paste.js
 * @author zhangkaixing
 * @date   2019-09-23
 * @link   https://www.codehui.net
 */

(function() {

    var factory = function (exports) {
        var $            = jQuery;           // if using module loader(Require.js/Sea.js).
        var pluginName   = "image-handle-paste";  // 定义插件名称

        //图片粘贴上传方法
        exports.fn.imagePaste = function() {
            var _this       = this;
            var cm          = _this.cm;
            var settings    = _this.settings;
            var editor      = _this.editor;
            var classPrefix = _this.classPrefix;
            var id       = _this.id;

            if(!settings.imageUpload || !settings.imageUploadURL){
                console.log('你还未开启图片上传或者没有配置上传地址');
                return false;
            }

            //监听粘贴板事件
            $('#' + id).on('paste', function (e) {

                var items = (e.clipboardData || e.originalEvent.clipboardData).items;

                //判断图片类型：从剪贴板项中安全地找出图片项，避免纯文本粘贴或空剪贴板时的越界访问
                var imageItem = items && items.length ? Array.prototype.filter.call(items, function (item) {
                    return item.type && item.type.indexOf('image') > -1;
                })[0] : null;

                if (imageItem) {
                    // 获取图片文件（从电脑拷贝或从网页复制的图片均可）
                    var file = imageItem.getAsFile();

                    /*生成blob
                    var blobImg = URL.createObjectURL(file);
                    */

                    /*base64
                    var reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onload = function (e) {
                        var base64Img = e.target.result //图片的base64
                    }
                    */

                    // 创建FormData对象进行ajax上传
                    // 根据粘贴图片的真实 MIME 推导扩展名，避免 gif/webp 等一律被命名为 .png
                    var extMap = {
                        'image/jpeg': 'jpg', 'image/jpg': 'jpg', 'image/png': 'png',
                        'image/gif': 'gif', 'image/webp': 'webp', 'image/bmp': 'bmp', 'image/svg+xml': 'svg'
                    };
                    var ext = extMap[(imageItem.type || '').toLowerCase()] || 'png';
                    var fileName = "file_" + Date.parse(new Date()) + "." + ext;

                    var forms = new FormData(document.forms[0]); //Filename
                    forms.append(classPrefix + "image-file", file, fileName); // 文件
                    forms.append("_method", 'POST' );

                    //调用imageDialog插件，弹出对话框
                    _this.executePlugin("imageDialog", "image-dialog/image-dialog");

                    _ajax(settings.imageUploadURL, forms, function(ret){
                        if(ret && ret.success == 1){
                            //数据格式可以自定义，但需要把图片地址写入到该节点里面
                            $("." + classPrefix + "image-dialog").find("input[data-url]").val(ret.url);
                            //cm.replaceSelection("![](" + ret.url  + ")");
                        } else {
                            //上传失败时给出用户可见的提示，与同级 image-dialog 插件保持一致
                            alert((ret && ret.message) ? ret.message : '图片上传失败');
                        }
                    })
                }
            })
        };
        // ajax上传图片 可自行处理
        var _ajax = function(url, data, callback) {
            $.ajax({
                "type": 'post',
                "cache": false,
                "url": url,
                "data": data,
                "processData": false,
                "contentType": false,
                "mimeType": "multipart/form-data",
                success: function(ret){
                    try {
                        callback(JSON.parse(ret));
                    } catch (e) {
                        // 服务端返回非法 JSON（如 HTML 错误页、会话过期重定向）时兜底提示，避免异常跳过反馈
                        callback({ success: 0, message: '服务端返回格式异常' });
                    }
                },
                error: function (err){
                    console.log('请求失败');
                    // 将请求失败也回调给上层，统一弹出用户提示
                    callback({ success: 0, message: '图片上传请求失败，请稍后重试' });
                }
            })
        }
    };

    // CommonJS/Node.js
    if (typeof require === "function" && typeof exports === "object" && typeof module === "object")
    {
        module.exports = factory;
    }
    else if (typeof define === "function")  // AMD/CMD/Sea.js
    {
        if (define.amd) { // for Require.js

            define(["editormd"], function(editormd) {
                factory(editormd);
            });

        } else { // for Sea.js
            define(function(require) {
                var editormd = require("./../../editormd");
                factory(editormd);
            });
        }
    }
    else
    {
        factory(window.editormd);
    }

})();