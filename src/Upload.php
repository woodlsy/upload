<?php

namespace woodlsy\upload;

use Exception;
use woodlsy\httpClient\HttpCurl;

class Upload
{

    public $type = 'image';

    public $fieldName = 'file';

    public $maxSize = '10M';

    protected static $units = array(
        'b' => 1,
        'k' => 1024,
        'm' => 1048576,
        'g' => 1073741824
    );

    public $path;
    /**
     * @var string 上传后新文件名
     */
    public $newFileName;

    public $fileInfo;
    /**
     * @var string 原文件名
     */
    public $fileName;
    public $fileType;
    public $fileError;
    public $fileSize;
    public $fileTmpName;
    /**
     * @var bool 是否压缩
     */
    public $compress       = false;
    public $compressWidth  = 0; // 压缩后宽度
    public $compressHeight = 0; // 压缩后高度
    public $compressAuto   = false; // 是否自动压缩

    /**
     * 远程上传服务器地址
     *
     * @var string
     */
    public $serverUrl = null;

    public function __construct($type = 'image')
    {
        $this->type = $type;
    }

    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    public function setMaxSize($size)
    {
        $this->maxSize = $size;
        return $this;
    }

    /**
     * 压缩图像
     *
     * @author yls
     * @param      $width
     * @param      $height
     * @param bool $auto
     * @return $this
     */
    public function compressImg($width, $height, $auto = false)
    {
        $this->compress       = true;
        $this->compressWidth  = $width;
        $this->compressHeight = $height;
        $this->compressAuto   = $auto;
        return $this;
    }

    public function setServerUrl($serverUrl)
    {
        $this->serverUrl = $serverUrl;
        if (!$this->path)
            $this->path = '/tmp';
        return $this;
    }

    public function Upload($fileName = null)
    {
        $this->drawFileInfo();
        $this->checkFileError();
        $this->checkFileSize();
        $this->checkPath();
        $this->checkFileType();
        $this->newFileName = $this->getFileName($fileName);
        if (file_exists($this->path . $this->newFileName)) {
            throw new Exception('该文件名已存在');
        }

        if (true === $this->compress) {
            if (!(new ImgCompress($this->fileTmpName))->compressImg($this->compressWidth, $this->compressHeight, $this->compressAuto)
                ->saveImg($this->path . $this->newFileName)) {
                throw new Exception('文件压缩失败');
            }
        } else {
            if (!move_uploaded_file($this->fileTmpName, $this->path . $this->newFileName)) {
                throw new Exception('文件上传失败');
            }
        }

        if ($this->serverUrl !== null) {
            return $this->remoteUpload();
        }

        $data          = [];
        $data['name']  = $this->newFileName;
        $data['title'] = $this->fileName;
        $data['url']   = $this->path . $this->newFileName;
        $data['size']  = $this->fileSize;
        $data['type']  = $this->fileType;

        return $data;
    }

    /**
     * 下载远程图片
     *
     * @param      $url
     * @param null $fileName
     * @return array
     * @throws Exception
     */
    public function urlUpload($url, $fileName = null)
    {
        $imgData           = (new HttpCurl())->setUrl($url)->get();
        $this->fileName    = pathinfo($url, PATHINFO_BASENAME);;
        $this->fileName    = current(explode('?', $this->fileName));
        $this->fileTmpName = '/tmp/' . $this->fileName;
        $fp                = @fopen($this->fileTmpName, 'w');
        @fwrite($fp, $imgData);
        fclose($fp);

        $this->newFileName = $this->getFileName($fileName);
        if (!copy($this->fileTmpName, $this->path . '/' . $this->newFileName)) {
            throw new Exception('文件上传失败');
        }
        unlink($this->fileTmpName);

        $data        = [];
        $data['url'] = $this->path . '/' . $this->newFileName;

        return $data;
    }

    /**
     * 远程上传
     * $result = json_encode(array('code'=>0, 'msg'=>'成功', 'name'=>...))
     *
     * @return mixed
     * @throws Exception
     */
    private function remoteUpload()
    {
        $curl = curl_init();
        $data = array('file' => new \CURLFile(realpath($this->path . $this->newFileName), $this->fileType, $this->fileName));
        curl_setopt($curl, CURLOPT_URL, $this->serverUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        if (curl_errno($curl)) {
            $errorMsg = curl_error($curl);
            curl_close($curl);
            throw new Exception($errorMsg);
        }
        curl_close($curl);
        $res = @json_decode($result, true);
        if (!isset($res['code'])) {
            throw new Exception('远程上传返回数据格式不对' . $result);
        }

        if ($res['code'] != 0) {
            throw new Exception($res['msg']);
        }

        unset($res['code'], $res['msg']);
        @unlink($this->path . $this->newFileName);
        return $res;
    }

    private function drawFileInfo()
    {
        if (!isset($_FILES[$this->fieldName])) {
            throw new Exception('上传文件不存在！');
        }
        $this->fileInfo    = $_FILES[$this->fieldName];
        $this->fileName    = $this->fileInfo['name'];
        $this->fileType    = $this->fileInfo['type'];
        $this->fileError   = $this->fileInfo['error'];
        $this->fileSize    = $this->fileInfo['size'];
        $this->fileTmpName = $this->fileInfo['tmp_name'];
    }

    /**
     * 检测上传文件
     *
     * @author yls
     * @return bool
     * @throws Exception
     */
    private function checkFileError()
    {
        switch ($this->fileError) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "上传文件大小超出了系统配置的upload_max_filesize大小";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                throw new Exception($message);
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                throw new Exception($message);
                break;

            default:
                $message = "Unknown upload error";
                return true;
                break;
        }
    }

    private function checkFileSize()
    {
        $size = $this->getSizeByte();
        if ($this->fileSize > $size) {
            throw new Exception("文件大小不能大于{$this->maxSize}");
        }
    }

    private function getSizeByte()
    {
        $value = (int) $this->maxSize;
        $unit  = strtolower(substr($this->maxSize, -1));
        if (isset(self::$units[$unit])) {
            $value = $value * self::$units[$unit];
        }
        return $value;
    }

    private function checkPath()
    {
        if (!$this->path) {
            throw new Exception("未设置上传路径");
        }

        if (substr($this->path, -1) == '/' || substr($this->path, -1) == '\\') {
            $this->path = substr($this->path, 0, -1);
        }

        if (!is_dir($this->path)) {
            self::directory($this->path);
        }

        if (!is_dir($this->path)) {
            throw new Exception("上传目录创建失败：" . $this->path);
        }

        if (!is_writable($this->path)) {
            throw new Exception("上传目录不可写入");
        }

        $this->path = $this->path . '/';
    }

    public function setUploadPath($path)
    {
        $this->path = $path;
        return $this;
    }

    private function getFileName($filename)
    {
        if (!$filename)
            return $this->createFileName();
        if (strpos('.', $filename) > 0) {
            return $filename;
        }
        return $filename . '.' . $this->getFileNameSuffix();
    }

    private function createFileName()
    {
        do {
            $newFileName = $this->randomString() . '.' . $this->getFileNameSuffix();
        } while (file_exists($this->path . $newFileName));

        return $newFileName;
    }

    private function randomString()
    {
        $string = time() . mt_rand(0, 9999);
        return $string;
    }

    private function getFileNameSuffix()
    {
        $suffixArr = explode('.', $this->fileName);
        return end($suffixArr);
    }

    private function checkFileType()
    {
        if ($this->type == 'image') {
            $fileTypeArr = ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif', 'image/jpg'];
        } elseif ($this->type == 'video') {
            $fileTypeArr = ['video/mp4', 'video/x-flv', 'video/3gpp', 'video/x-msvideo', 'video/x-ms-wmv', 'video/quicktime', 'video/x-msvideo', 'video/x-sgi-movie'];
        } else {
            $fileTypeArr = [];
        }
        if (!empty($fileTypeArr) && !in_array($this->fileType, $fileTypeArr, true)) {
            throw new Exception("非法文件类型");
        }
    }

    /**
     * 自动创建目录
     *
     * @param string $dir
     * @return boolean
     */
    public static function directory($dir)
    {
        return is_dir($dir) or self::directory(dirname($dir)) and @mkdir($dir, 0777);
    }
}