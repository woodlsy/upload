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
     * @return $this
     */
    public function compressImg($width, $height = 0, $auto = false)
    {
        if (true === $auto) { // 自动缩放
            if (empty($width)) {
                if ($height >= $this->imageInfo['height']) {
                    $this->canvasWidth  = $this->imageInfo['width'];
                    $this->canvasHeight = $this->imageInfo['height'];
                } else {
                    $percent            = round($height / $this->imageInfo['height'], 2);
                    $this->canvasWidth  = (int) ($this->imageInfo['width'] * $percent);
                    $this->canvasHeight = $height;
                }
            }elseif (empty($height)) {
                if ($width >= $this->imageInfo['width']) {
                    $this->canvasWidth  = $this->imageInfo['width'];
                    $this->canvasHeight = $this->imageInfo['height'];
                } else {
                    $percent            = round($width / $this->imageInfo['width'], 2);
                    $this->canvasHeight  = (int) ($this->imageInfo['height'] * $percent);
                    $this->canvasWidth = $width;
                }
            }elseif (empty($width) && empty($height)) {
                $this->canvasWidth  = $this->imageInfo['width'];
                $this->canvasHeight = $this->imageInfo['height'];
            } else {
                if ($width >= $this->imageInfo['width'] && $height >= $this->imageInfo['height']) {
                    $this->canvasWidth  = $this->imageInfo['width'];
                    $this->canvasHeight = $this->imageInfo['height'];
                } else {
                    if ($width > $height) {
                        $percent = round($height / $this->imageInfo['height'], 2);
                    } else {
                        $percent = round($width / $this->imageInfo['width'], 2);
                    }
                    $this->canvasWidth  = (int) ($this->imageInfo['width'] * $percent);
                    $this->canvasHeight = (int) ($this->imageInfo['height'] * $percent);
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
     * 保存图像为文件
     *
     * @author yls
     * @param $saveName
     * @return bool
     */
    public function saveImg($saveName)
    {
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
        $this->_thumpImage();
    }

    /**
     * 内部：操作图片
     */
    private function _thumpImage()
    {
        $image_thump = imagecreatetruecolor($this->canvasWidth, $this->canvasHeight);
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
        imagedestroy($this->image);
    }

}