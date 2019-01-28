<?php
namespace woodlsy\upload;

class Upload {
    
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
    public $newFileName;
    
    public $fileInfo;
    public $fileName;
    public $fileType;
    public $fileError;
    public $fileSize;
    public $fileTmpName;
    
    public function __construct()
    {
        
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
    
    
    public function Upload($filename=null)
    {
        $this->drawFileInfo();
        $this->checkFileError();
        $this->checkFileSize();
        $this->checkPath();
        $this->checkFileType();
        $this->newFileName = $this->getFileName($filename);
        if(file_exists($this->path.$this->newFileName)){
            throw new \Exception('该文件名已存在');
        }
        
        if(!move_uploaded_file($this->fileTmpName, $this->path.$this->newFileName)){
            throw new \Exception('文件上传失败');
        }
        
        $data = [];
        $data['name'] = $this->newFileName;
        $data['url']  = $this->path.$this->newFileName;
        
        return $data;
    }
    
    private function drawFileInfo()
    {
        if(!isset($_FILES[$this->fieldName]))throw new \Exception('上传文件不存在！');
        $this->fileInfo    = $_FILES[$this->fieldName];
        $this->fileName    = $this->fileInfo['name'];
        $this->fileType    = $this->fileInfo['type'];
        $this->fileError   = $this->fileInfo['error'];
        $this->fileSize    = $this->fileInfo['size'];
        $this->fileTmpName = $this->fileInfo['tmp_name'];
    }
    
    private function checkFileError()
    {
        switch ($this->fileError) {
            case UPLOAD_ERR_INI_SIZE:
                $message = "上传文件大小超出了系统配置的upload_max_filesize大小";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $message = "The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_PARTIAL:
                $message = "The uploaded file was only partially uploaded";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_NO_FILE:
                $message = "No file was uploaded";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $message = "Missing a temporary folder";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $message = "Failed to write file to disk";
                throw new \Exception($message);
                break;
            case UPLOAD_ERR_EXTENSION:
                $message = "File upload stopped by extension";
                throw new \Exception($message);
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
        if($this->fileSize > $size){
            throw new \Exception("文件大小不能大于{$this->maxSize}");
        }
    }
    
    private function getSizeByte()
    {
        $value = (int)$this->maxSize;
        $unit = strtolower(substr($this->maxSize, -1));
        if (isset(self::$units[$unit])) {
            $value = $value * self::$units[$unit];
        }
        return $value;
    }
    
    private function checkPath()
    {
        if(!$this->path){
            throw new \Exception("未设置上传路径");
            return false;
        }
        
        if(substr($this->path, -1) == '/' || substr($this->path, -1) == '\\'){
            $this->path = substr($this->path, 0, -1);
        }
        
        if(!is_dir($this->path)){
            @mkdir($this->path, '0777', true);
        }
        
        if(!is_dir($this->path)){
            throw new \Exception("上传地址创建不成功");
        }
        
        if(!is_writable($this->path)){
            throw new \Exception("上传地址不可写入");
        }
        
        $this->path = $this->path.'/';
    }
    
    public function setUploadPath($path)
    {
        $this->path = $path;
        return $this;
    }
    
    private function getFileName($filename)
    {
        if(!$filename)return $this->createFileName();
        if(strpos('.', $filename) > 0){
            return $filename;
        }
        return $filename.$this->getFileNameSuffix();
    }
    
    private function createFileName()
    {
        do{
            $newFileName = $this->randomString().'.'.$this->getFileNameSuffix();
        }while(file_exists($this->path.$newFileName));
        
        return $newFileName;
    }
    
    private function randomString()
    {
        $string = time().mt_rand(0, 9999);
        return $string;
    }
    
    private function getFileNameSuffix()
    {
        $suffixArr = explode('.', $this->fileName);
        return end($suffixArr);
    }
    
    private function checkFileType()
    {
        if($this->type == 'image'){
            if(!in_array($this->fileType, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/gif'])){
                throw new \Exception("非法文件类型");
            }
        }
    }
}