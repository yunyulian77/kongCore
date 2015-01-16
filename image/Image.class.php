<?php

class Image {

    public $img;
    public $info;

    /* 缩略图相关常量定义 */

    const IMAGE_THUMB_SCALE = 1; //常量，标识缩略图等比例缩放类型
    const IMAGE_THUMB_FILLED = 2; //常量，标识缩略图缩放后填充类型
    const IMAGE_THUMB_CENTER = 3; //常量，标识缩略图居中裁剪类型
    const IMAGE_THUMB_NORTHWEST = 4; //常量，标识缩略图左上角裁剪类型
    const IMAGE_THUMB_SOUTHEAST = 5; //常量，标识缩略图右下角裁剪类型
    const IMAGE_THUMB_FIXED = 6; //常量，标识缩略图固定尺寸缩放类型

    /* 水印相关常量定义 */
    
    const IMAGE_WATER_NORTHWEST = 1; //常量，标识左上角水印
    const IMAGE_WATER_NORTH = 2; //常量，标识上居中水印
    const IMAGE_WATER_NORTHEAST = 3; //常量，标识右上角水印
    const IMAGE_WATER_WEST = 4; //常量，标识左居中水印
    const IMAGE_WATER_CENTER = 5; //常量，标识居中水印
    const IMAGE_WATER_EAST = 6; //常量，标识右居中水印
    const IMAGE_WATER_SOUTHWEST = 7; //常量，标识左下角水印
    const IMAGE_WATER_SOUTH = 8; //常量，标识下居中水印
    const IMAGE_WATER_SOUTHEAST = 9; //常量，标识右下角水印

    function __construct($imgname = null) {
        $imgname && $this->open($imgname);
    }

    /**
     * 打开图像并创建一个图像资源
     * @param type $imgname  打开图像文件名
     */
    public function open($imgname) {
        if (!is_file($imgname)) {
            die("文件不存在");
        }
        $info = getimagesize($imgname);
        $this->info = array(
            'width' => $info[0],
            'height' => $info[1],
            'type' => image_type_to_extension($info[2], false),
            'mime' => $info['mime'],
            'filename' => basename($imgname)
        );
        //销毁已存在的图像
        empty($this->img) || imagedestroy($this->img);
        $func = "imagecreatefrom{$this->info['type']}";
        $this->img = $func($imgname);
    }

    /**
     * 计算裁剪区域和保存图片各个参数
     * @param type $src 原始图片
     * @param type $width 生成缩略图的宽度
     * @param type $height 生成缩略图的高度
     * @param type $path  保存缩略图的位置
     */
    public function thumb($path, $width = 0, $height = 0, $type = Image::IMAGE_THUMB_SCALE) {
        $x = $y = 0;
        //原始图像大小
        $w = $this->info['width'];
        $h = $this->info['height'];
        if ($w < $width && $h < $height) {
            die("图片太小");
        }
        switch ($type) {
            //等比例缩放
            case Image::IMAGE_THUMB_SCALE:
                $ratio = min($width / $w, $height / $h);
                $width = intval($w * $ratio);
                $height = intval($h * $ratio);
                $x = 0;
                $y = 0;
                break;

            //等比例缩放填充
            case Image::IMAGE_THUMB_FILLED:
                $ratio = min($width / $w, $height / $h);
                $neww = intval($w * $ratio);
                $newh = intval($h * $ratio);
                $x = ($width - $neww) / 2;
                $y = ($height - $newh) / 2;
                $new = imagecreatetruecolor($width, $height);
                $imageColor = imagecolorallocate($new, 255, 255, 255);
                imagefill($new, 0, 0, $imageColor);
                imagecopyresampled($new, $this->img, $x, $y, 0, 0, $neww, $newh, $w, $h);
                $dst = rtrim($path, "/") . "/" . "thumb_" . $width . "_" . $height . "_" . $this->info['filename'];
                $this->save($dst, $new);
                return;

            //缩略图居中裁剪类型
            case Image::IMAGE_THUMB_CENTER:
                $ratio = max($width / $w, $height / $h);
                $x = ($w - $width / $ratio) / 2;
                $y = ($h - $height / $ratio) / 2;
                $w = $width / $ratio;
                $h = $height / $ratio;
                break;

            //缩略图左上角裁剪类型
            case Image::IMAGE_THUMB_NORTHWEST:
                $ratio = max($width / $w, $height / $h);
                $w = $width / $ratio;
                $h = $height / $ratio;
                $x = 0;
                $y = 0;
                break;

            //缩略图右下角裁剪类型
            case 5:
                $ratio = max($width / $w, $height / $h);
                $x = ($w - $width / $ratio);
                $y = ($h - $height / $ratio);
                $w = $width / $ratio;
                $h = $height / $ratio;
                break;

            case 6:

                break;
        }
        $this->crop($path, $w, $h, $x, $y, $width, $height);
    }

    /**
     * 
     * @param type $w 裁剪区域宽度
     * @param type $h 裁剪区域高度
     * @param type $x 裁剪区域坐标X
     * @param type $y 裁剪区域坐标Y
     * @param type $width 保存图片的宽度
     * @param type $height  保存图片的高度
     */
    public function crop($path, $w, $h, $x, $y, $width, $height) {
        $new = imagecreatetruecolor($width, $height);
        $imageColor = imagecolorallocate($new, 255, 255, 255);
        imagefill($new, 0, 0, $imageColor);
        imagecopyresampled($new, $this->img, 0, 0, $x, $y, $width, $height, $w, $h);
        //处理透明度
        if ($this->info['type'] == "gif" || $this->info['type'] == "png") {
            imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }
        $dst = rtrim($path, "/") . "/" . "thumb_" . $width . "_" . $height . "_" . $this->info['filename'];
        $this->save($dst, $new);
    }

    /**
     * 保存图像
     * @param type $dst 目标图像保存位置
     * @param type $new 目标图像新资源
     */
    public function save($dst, $new) {
        switch ($this->info['type']) {
            case 'gif':
                imagegif($new, $dst);
                break;
            case "jpg":
            case "jpeg":
                imagejpeg($new, $dst);
                break;
            case "png":
                imagepng($new, $dst);
                break;
        }
    }

    /**
     * 图片水印
     * @param type $water 水印图像
     * @param type $locate 水印位置
     * @param type $alpha 透明度
     */
    public function water($waterImg, $locate = Image::IMAGE_WATER_SOUTHEAST, $path = "./", $alpha = 80) {

        if (!is_file($waterImg)) {
            die("水印图片不是一个文件");
        }
        $info = getimagesize($waterImg);
        if (false === $info) {
            die("图片不合法");
        }
        $fun = "imagecreatefrom" . image_type_to_extension($info[2], false);
        $water = $fun($waterImg);

        switch ($locate) {
            //图片左上角水印
            case Image::IMAGE_WATER_NORTHWEST:
                $x = 0;
                $y = 0;
                break;
            //图片上居中水印
            case Image::IMAGE_WATER_NORTH:
                $x = ($this->info['width'] - $info[0]) / 2;
                $y = 0;
                break;
            //图片右上角水印
            case Image::IMAGE_WATER_NORTHEAST:
                $x = $this->info['width'] - $info[0];
                $y = 0;
                break;
            //图片左居中水印
            case Image::IMAGE_WATER_WEST:
                $x = 0;
                $y = ($this->info['height'] - $info[1]) / 2;
                break;
            //图片居中水印
            case Image::IMAGE_WATER_CENTER:
                $x = ($this->info['width'] - $info[0]) / 2;
                $y = ($this->info['height'] - $info[1]) / 2;
                break;
            //图片右居中水印
            case Image::IMAGE_WATER_EAST:
                $x = $this->info['width'] - $info[0];
                $y = ($this->info['height'] - $info[1]) / 2;
                break;
            //图片左下水印
            case Image::IMAGE_WATER_SOUTHWEST:
                $x = 0;
                $y = $this->info['height'] - $info[1];
                break;
            //图片下居中水印
            case Image::IMAGE_WATER_SOUTH:
                $x = ($this->info['width'] - $info[0]) / 2;
                $y = $this->info['height'] - $info[1];
                break;
            //图片右下角水印
            case Image::IMAGE_WATER_SOUTHEAST:
                $x = $this->info['width'] - $info[0];
                $y = $this->info['height'] - $info[1];
                break;
        }
        //水印加图片时使用imagecopymerge时$dst是原图像
        imagecopymerge($this->img, $water, $x, $y, 0, 0, $info[0], $info[1], $alpha);
        $dst = rtrim($path, "/") . "/" . "water_img_" . $this->info['filename'];
        $this->save($dst, $this->img);
    }

    /**
     * 文字水印
     * @param type $text  文字内容
     * @param type $font  文字字体路径
     * @param type $size  文字大小
     * @param type $color 文字颜色
     * @param type $locate 文字水印位置
     * @param type $angele 文字角度
     */
    public function text($text, $fontfile, $size, $color = "#000000", $locate = Image::IMAGE_WATER_NORTHWEST, $path = "./", $angle = "0") {

        $info = imagettfbbox($size, $angle, $fontfile, $text);
        $minX = min($info[0], $info[2], $info[4], $info[6]);
        $minY = min($info[1], $info[3], $info[5], $info[7]);
        $maxX = max($info[0], $info[2], $info[4], $info[6]);
        $maxY = max($info[1], $info[3], $info[5], $info[7]);
        $x = abs($minX);
        $y = abs($minY);
        $w = $maxX - $minX;
        $h = $maxY - $minY;
        if (is_string($color)) {
            $colorArr = str_split(substr($color, 1), 2);
        }
        $colorArr = array_map('hexdec', $colorArr);
        $col = imagecolorallocate($this->img, $colorArr[0], $colorArr[1], $colorArr[2]);
        switch ($locate) {
            //左上
            case Image::IMAGE_WATER_NORTHWEST:
                break;
            //上居中
            case Image::IMAGE_WATER_NORTH:
                $x += ($this->info['width'] - $w) / 2;
                break;
            //右上角
            case Image::IMAGE_WATER_NORTHEAST:
                $x += $this->info['width'] - $w;
                break;
            //左居中
            case Image::IMAGE_WATER_WEST:
                $y += ($this->info['height'] - $h) / 2;
                break;
            //居中
            case Image::IMAGE_WATER_CENTER:
                $x += ($this->info['width'] - $w) / 2;
                $y += ($this->info['height'] - $h) / 2;
                break;
            //右居中
            case Image::IMAGE_WATER_EAST:
                $x += $this->info['width'] - $w;
                $y += ($this->info['height'] - $h) / 2;
                break;
            //左下角
            case Image::IMAGE_WATER_SOUTHWEST:
                $y += $this->info['height'] - $h;
                break;
            //下居中
            case Image::IMAGE_WATER_SOUTH:
                $x += ($this->info['width'] - $w) / 2;
                $y += $this->info['height'] - $h;
                break;
            //右下角
            case Image::IMAGE_WATER_SOUTHEAST:
                $x += $this->info['width'] - $w;
                $y += $this->info['height'] - $h;
                break;
        }
        imagettftext($this->img, $size, $angle, $x, $y, $col, $fontfile, $text);
        $dst = rtrim($path, "/") . "/" . "water_text_" . $this->info['filename'];
        $this->save($dst, $this->img);
    }
    
    /**
     * 析构方法，用于销毁图像资源
     */
    public function __destruct() {
        empty($this->img) || imagedestroy($this->img);
    }

}

?>
