<?php

namespace woodlsy\upload;

class ImgCompress
{
    private $src;
    private $image;
    private $imageInfo;
    private $percent = 0.5;

    /**
     * @var int 画布宽度
     */
    private $canvasWidth;

    /**
     * @var int 画布高度
     */
    private $canvasHeight;
    private $isCompress = true;


    /**
     * @author yls
     * ImgCompress constructor.
     * @param     $src
     */
    public function __construct($src)
    {
        $this->src = $src;
        list($width, $height, $type, $attr) = getimagesize($this->src);
        $this->imageInfo = array(
            'width'  => $width,
            'height' => $height,
            'type'   => image_type_to_extension($type, false),
            'attr'   => $attr
        );

    }

    /**
     * 压缩图片
     *
     * @author yls
     * @param int  $width 当auto=false时，为等比例压缩值，小于等于1。否则为画布宽度
     * @param int  $height 画布高度
     * @param bool $auto 为false时，固定宽度和高度缩放；
     *                   为true时是自动缩放，width为0时，以固定高度等比例缩放；
     *                   height为0时，以固定宽度等比例缩放；
     *                  都不为0时，以原图像最长的一边固定为width或height，等比例缩放（即width和height构成一盒子，新图像最长的一边不能超出盒子）；
     * @param bool $jumpAnimatedGif true 跳过gif动画，不压缩
     * @return $this
     */
    public function compressImg($width, $height = 0, $auto = false, $jumpAnimatedGif = true)
    {
        $isAnimatedGif = $this->isAnimatedGif($this->src);
        if (true === $jumpAnimatedGif && true === $isAnimatedGif) {
            $this->isCompress = false;
            return $this;
        }
        if (true === $auto) { // 自动缩放
            if (empty($width) && !empty($height)) {
                if ($height >= $this->imageInfo['height']) {
                    $this->isCompress = false;
                    return $this;
                } else {
                    $percent            = round($height / $this->imageInfo['height'], 2);
                    $this->canvasWidth  = (int) ($this->imageInfo['width'] * $percent);
                    $this->canvasHeight = $height;
                }
            } elseif (!empty($width) && empty($height)) {
                if ($width >= $this->imageInfo['width']) {
                    $this->isCompress = false;
                    return $this;
                } else {
                    $percent            = round($width / $this->imageInfo['width'], 2);
                    $this->canvasHeight = (int) ($this->imageInfo['height'] * $percent);
                    $this->canvasWidth  = $width;
                }
            } elseif (empty($width) && empty($height)) {
                $this->canvasWidth  = $this->imageInfo['width'];
                $this->canvasHeight = $this->imageInfo['height'];
            } else {
                if ($width >= $this->imageInfo['width'] && $height >= $this->imageInfo['height']) {
                    $this->canvasWidth  = $this->imageInfo['width'];
                    $this->canvasHeight = $this->imageInfo['height'];
                } else {
                    $percent            = $width > $height ? $height / $this->imageInfo['height'] : $width / $this->imageInfo['width'];
                    $this->canvasWidth  = $width > $height ? (int) ($this->imageInfo['width'] * $percent) : $width;
                    $this->canvasHeight = $width > $height ? $height : (int) ($this->imageInfo['height'] * $percent);
                }
            }
        } elseif (!empty($height)) { // 按固定宽高缩放
            $this->canvasWidth  = $width;
            $this->canvasHeight = $height;
        } else { // 按百分比缩放
            $this->percent = $width;
        }
        $this->_openImage();
        return $this;
    }

    /**
     * 判断是否是gif动画
     *
     * @author yls
     * @param $filename
     * @return bool
     */
    public function isAnimatedGif($filename)
    {
        $fp          = fopen($filename, 'rb');
        $fileContent = fread($fp, filesize($filename));
        fclose($fp);
        return strpos($fileContent, chr(0x21) . chr(0xff) . chr(0x0b) . 'NETSCAPE2.0') !== FALSE;
    }

    /**
     * 保存图像为文件
     *
     * @author yls
     * @param $saveName
     * @return bool
     */
    public function saveImg($saveName)
    {
        if (false === $this->isCompress) {
            return move_uploaded_file($this->src, $saveName);
        }
        return $this->_saveImage($saveName);
    }

    /**
     * 输出图像
     *
     * @author yls
     */
    public function outImg()
    {
        $this->_showImage();
    }

    /**
     * 内部：打开图片
     */
    private function _openImage()
    {

        $fun         = "imagecreatefrom" . $this->imageInfo['type'];
        $this->image = $fun($this->src);
        imagesavealpha($this->image, true);       //这里很重要;
        $this->_thumpImage();
    }

    /**
     * 内部：操作图片
     */
    private function _thumpImage()
    {
        $image_thump = imagecreatetruecolor($this->canvasWidth, $this->canvasHeight);
        imagealphablending($image_thump, false);//这里很重要,意思是不合并颜色,直接用$img图像颜色替换,包括透明色;
        imagesavealpha($image_thump, true);     //这里很重要,意思是不要丢了$thumb图像的透明色;
        //将原图复制带图片载体上面，并且按照一定比例压缩,极大的保持了清晰度
        imagecopyresampled($image_thump, $this->image, 0, 0, 0, 0, $this->canvasWidth, $this->canvasHeight, $this->imageInfo['width'], $this->imageInfo['height']);
        imagedestroy($this->image);
        $this->image = $image_thump;
    }

    /**
     * 输出图片:保存图片则用saveImage()
     */
    private function _showImage()
    {
        header('Content-Type: image/' . $this->imageInfo['type']);
        $funcs = "image" . $this->imageInfo['type'];
        $funcs($this->image);
    }

    /**
     * 保存图片到硬盘
     *
     * @author yls
     * @param $dstImgName 1、可指定字符串不带后缀的名称，使用源图扩展名 。2、直接指定目标图片名带扩展名。
     * @return bool
     */
    private function _saveImage($dstImgName)
    {
        if (empty($dstImgName))
            return false;
        $allowImgs = ['.jpg', '.jpeg', '.png', '.bmp', '.wbmp', '.gif'];   //如果目标图片名有后缀就用目标图片扩展名 后缀，如果没有，则用源图的扩展名
        $dstExt    = strrchr($dstImgName, ".");
        $sourseExt = strrchr($this->src, ".");
        if (!empty($dstExt))
            $dstExt = strtolower($dstExt);
        if (!empty($sourseExt))
            $sourseExt = strtolower($sourseExt);

        //有指定目标名扩展名
        if (!empty($dstExt) && in_array($dstExt, $allowImgs)) {
            $dstName = $dstImgName;
        } elseif (!empty($sourseExt) && in_array($sourseExt, $allowImgs)) {
            $dstName = $dstImgName . $sourseExt;
        } else {
            $dstName = $dstImgName . $this->imageInfo['type'];
        }
        $funcs = "image" . $this->imageInfo['type'];
        return $funcs($this->image, $dstName);
    }

    /**
     * 销毁图片
     */
    public function __destruct()
    {
        if ($this->image) {
            imagedestroy($this->image);
        }
    }

}