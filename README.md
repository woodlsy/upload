# upload
PHP的上传类

**一、普通上传**
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

**二、远程上传**

本地服务端代码:

```php
$serverUrl = 'http://www.img.com/upload/img?project=test';
$data = (new Upload())->setServerUrl($serverUrl)->upload();
```

远程服务端代码：

```php
try {
    $project = $_GET['project'];
    $relativePath = $project.'/'.date('Ymd');
    $path = '/data/upload/'.$relativePath;
    $data = (new Upload())->setMaxSize('2M')->setUploadPath($path)->upload();
    $data['url'] = $relativePath.'/'.$data['name'];
}catch (\Exception $e){
    echo '错误提示：'.$e->getMessage();
}
//图片访问地址为 http://www.img.com/$data['url']
```


温馨提示：
  暂时只支持上传图片，单文件上传
