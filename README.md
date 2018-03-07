# PHP上传图片类
PHP上传图片类，支持裁切图片、生成多尺寸及各限制条件等。

使用代码示例：
```php
require __DIR__ . 'UploadImage.class.php';
$class = new \Classes\UploadImage();
$class->setFile($_FILES['pic']);
$class->setPath(__DIR__, 'upload/{yyyy}{mm}{dd}/');
$class->setVerifyImageSize(300, 300);
$class->setFileName('{time}{rand:20}');
$class->setVerifySuffix(['png']);
$class->setVerifyMbByte(2);
$class->setSize(200, 200);
$class->setSize(100, 100);
$class->upload();
$result = $class->getResult();
$data = $class->getFileName();
$log = $class->getLog();
var_dump($result);
var_dump($data);
var_dump($log);
```
