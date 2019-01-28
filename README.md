# upload
PHP的上传类

使用方式：
```html
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="file" value=""/>
    <input type="submit" value="Upload File"/>
</form>
```



```php
<?php
use woodlsy\upload\Upload;

    try {
         $size      = '2M';         //上传文件最大尺寸
         $path      = '/upload';    //上传文件保存地址
         $fileName  = time();       //上传文件名称，可不填，自动生成唯一文件名
         $fieldName = 'file';       //上传字段名称
         $data = (new Upload())->setFieldName($fieldName)->setMaxSize($size)->setUploadPath($path)->upload(fileName);
    }catch (\Exception $e){
         echo '错误提示：'.$e->getMessage();
    }
```

温馨提示：
  暂时只支持上传图片，单文件上传
