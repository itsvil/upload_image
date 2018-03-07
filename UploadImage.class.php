<?php

/***
 * 上传图片
 * @方淞 WeChat:wnfangsong E-mail:wnfangsong@163.com
 * @LINK:http://www.xpzfs.com
 * @TIME:2018-01-24
 */

namespace Classes;
//[version test1.0]
class UploadImage
{

    private $_inputFile = null;
    private $_newFile = ''; //新文件路径
    private $_path = '';
    private $_returnPath = '';
    private $_fileName = '';
    private $_imageSuffix = '';
    private $_image = null;
    private $_cFileByte = 0;
    private $_cImageSize = []; //多尺寸图
    private $_cVerifyImageSize = []; //验证尺寸
    private $_cImageCutSize = []; //裁切尺寸
    private $_cOriginalSize = []; //原图尺寸
    private $_ratio = 0; //缩放图片比例，如果缩放后比例大于设置的值则留白，0为任何比例下都不留白
    private $_imageSuffixList = [
        255216 => 'jpg',
        13780 => 'png',
        7173 => 'gif'
    ];
    private $_result = true;
    private $_log = '';

    /**
     * 记录日志
     * @param bool $result
     * @param string $log
     * @return void
     */
    private function _putLog(bool $result, string $log)
    {
        $this->_result = $result;
        $this->_log = $log;
    }

    /**
     * 产生随机数
     * @param int $len
     * @return string
     */
    private function _createRand(int $len) : string
    {
        if ($len < 15) {
            $len = 15;
        }
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz';
        $string = time();
        $len -= strlen($string);
        for (; $len >= 1; $len--) {
            $position1 = mt_rand() % strlen($chars);
            $position2 = mt_rand() % strlen($string);
            $string = substr_replace($string, substr($chars, $position1, 1), $position2, 0);
        }
        return $string;
    }

    /**
     * 获取文件类型Code
     * @param string $file_name
     * @return int
     */
    private function _getFileSuffixCode(string $file_name) : int
    {
        $file = fopen($file_name, 'rb');
        $bin = fread($file, 2);
        fclose($file);
        $str_info = unpack('C2chars', $bin);
        $code = intval($str_info['chars1'] . $str_info['chars2']);
        return $code;
    }

    /**
     * 获取图片真实尺寸
     * @param string $file_name
     * @return array
     */
    private function _getImageSize(string $file_name)
    {
        $size = getimagesize($file_name);
        if ($size[2] === IMAGETYPE_JPEG) {
            //读取相片拍照的方向（除IOS竖着拍照外其他图片全部都是1）
            $exif = exif_read_data($file_name, 'IFD0');
            $orientation = isset($exif['Orientation']) ? $exif['Orientation'] : 1;
            //校正尺寸
            if ($orientation >= 5 && $orientation <= 8) {
                return [
                    'width' => $size[1],
                    'height' => $size[0]
                ];
            }
        }
        return [
            'width' => $size[0],
            'height' => $size[1]
        ];
    }

    /**
     * 计算图片缩放尺寸
     * @param int $new_width
     * @param int $new_height
     * @return array
     */
    private function _getImageZoomSize(int $new_width, int $new_height) : array
    {
        //设置图片的宽不能为自适应
        if ($new_width === 0) {
            return [];
        }
        //设置变量
        $dst_x = 0;
        $dst_y = 0;
        list($pic_width, $pic_height) = getimagesize($this->_newFile);
        //设缩图的宽固定，则高为：
        $dst_width = $new_width;
        $dst_height = floor($pic_height * $new_width / $pic_width);
        $img_height = $dst_height;
        //如果设置图片的高大于0则：
        if ($new_height > 0) {
            //如果缩图的高大于用户定义的高，则让图片垂直居中
            if ($dst_height > $new_height) {
                $dst_y = -floor(($dst_height - $new_height) / 2);
            } else {
                //缩图的高固定，则宽为：
                $dst_height = $new_height;
                $dst_width = floor($pic_width * $new_height / $pic_height);
                $dst_x = -floor(($dst_width - $new_width) / 2);
            }
            $img_height = $new_height;
            //如果设置比例的情况下，图片放大或缩小后的尺寸是设置图片的值以上则留白
            if ($this->_ratio > 0) {
                $ratio_width = $dst_width / $new_width;
                $ratio_height = $dst_height / $new_height;
                $ratio = $ratio_width > $ratio_height ? $ratio_width : $ratio_height;
                if ($ratio > $this->_ratio) {
                    $dst_x = 0;
                    $dst_y = 0;
                    //如果宽比大于高比的情况下：
                    if ($ratio_width > $ratio_height) {
                        $dst_width = $new_width;
                        $dst_height = floor($pic_height * $new_width / $pic_width);
                        $dst_y = floor(($new_height - $dst_height) / 2);
                    } else {
                        $dst_height = $new_height;
                        $dst_width = floor($pic_width * $new_height / $pic_height);
                        $dst_x = floor(($new_width - $dst_width) / 2);
                    }
                }
            }
        }
        return [
            'canvas_width' => $new_width,
            'canvas_height' => $img_height,
            'dst_width' => $dst_width,
            'dst_height' => $dst_height,
            'dst_x' => $dst_x,
            'dst_y' => $dst_y,
            'pic_width' => $pic_width,
            'pic_height' => $pic_height
        ];
    }

    /**
     * 计算裁切图片尺寸
     * @return array
     */
    private function _getCutImageSize() : array
    {
        //设置变量
        list($pic_w, $pic_h) = getimagesize($this->_newFile);
        $src_img_w = $pic_w;
        $src_img_h = $pic_h;
        //计算旋转后的尺寸
        if ($this->_cImageCutSize['rotate'] != 0) {
            $deg = abs($this->_cImageCutSize['rotate']) % 180;
            $arc = ($deg > 90 ? (180 - $deg) : $deg) * M_PI / 180;
            $src_img_w = ($pic_w * cos($arc) + $pic_h * sin($arc)) - 1;
            $src_img_h = ($pic_w * sin($arc) + $pic_h * cos($arc)) - 1;
        }
        //计算宽高坐标
        $tmp_img_w = $this->_cImageCutSize['width'];
        $tmp_img_h = $this->_cImageCutSize['height'];
        $src_x = $this->_cImageCutSize['x'];
        $src_y = $this->_cImageCutSize['y'];
        $src_w = $src_h = $dst_x = $dst_y = $dst_w = $dst_h = 0;
        if ($src_x <= -$tmp_img_w || $src_x > $src_img_w) {
            $src_x = 0;
        } else if ($src_x <= 0) {
            $dst_x = -$src_x;
            $src_x = 0;
            $src_w = $dst_w = min($src_img_w, $tmp_img_w + $src_x);
        } else if ($src_x <= $src_img_w) {
            $src_w = $dst_w = min($tmp_img_w, $src_img_w - $src_x);
        }
        if ($src_w <= 0 || $src_y <= -$tmp_img_h || $src_y > $src_img_h) {
            $src_y = 0;
        } else if ($src_y <= 0) {
            $dst_y = -$src_y;
            $src_y = 0;
            $src_h = $dst_h = min($src_img_h, $tmp_img_h + $src_y);
        } else if ($src_y <= $src_img_h) {
            $src_h = $dst_h = min($tmp_img_h, $src_img_h - $src_y);
        }
        //返回
        return [
            'dst_w' => $dst_w,
            'dst_h' => $dst_h,
            'dst_x' => $dst_x,
            'dst_y' => $dst_y,
            'src_w' => $src_w,
            'src_h' => $src_h,
            'src_x' => $src_x,
            'src_y' => $src_y
        ];
    }

    /**
     * 创建新的图像
     * @return void
     */
    private function _getImage()
    {
        switch ($this->_imageSuffix) {
            case 'gif':
                $this->_image = imagecreatefromgif($this->_newFile);
                break;
            case 'jpg':
                $this->_image = imagecreatefromjpeg($this->_newFile);
                break;
            case 'png':
                $this->_image = imagecreatefrompng($this->_newFile);
                imagesavealpha($this->_image, true);
                break;
            default:
                break;
        }
    }

    /**
     * 保存新的图像
     * @param $image
     * @param string $file_name
     * @return void
     */
    private function _saveImage($image, string $file_name)
    {
        switch ($this->_imageSuffix) {
            case 'gif':
                imagegif($image, $file_name);
                break;
            case 'jpg':
                imagejpeg($image, $file_name);
                break;
            case 'png':
                imagepng($image, $file_name);
                break;
            default:
                break;
        }
        imagedestroy($this->_image);
        $this->_image = null;
    }

    /**
     * 验证图片尺寸
     * @return bool
     */
    private function _verifyImageSize() : bool
    {
        //获取图片真实尺寸
        $size = $this->_getImageSize($this->_inputFile['tmp_name']);
        $result = true;
        foreach ($this->_cVerifyImageSize as $k => $v) {
            switch ($v['symbol']) {
                case '>=':
                    if ($size[$k] >= $v['value']) {
                        $result = false;
                    }
                    break;
                case '<=':
                    if ($size[$k] <= $v['value']) {
                        $result = false;
                    }
                    break;
                case '>':
                    if ($size[$k] > $v['value']) {
                        $result = false;
                    }
                    break;
                case '<':
                    if ($size[$k] < $v['value']) {
                        $result = false;
                    }
                    break;
                case '!=':
                    if ($size[$k] != $v['value']) {
                        $result = false;
                    }
                    break;
                default:
                    break;
            }
        }
        return $result;
    }

    /**
     * 判断上传文件是否错误
     * @return void
     */
    private function _fileError()
    {
        if (null === $this->_inputFile) {
            $this->_putLog(false, '没有设置文件参数。');
            return;
        }
        if (!is_uploaded_file($this->_inputFile['tmp_name'])) {
            $this->_putLog(false, '非通过HTTP POST形式上传文件。');
            return;
        }
        $log_list = [
            1 => '文件超出服务器所设大小限制值。',
            2 => '文件超出表单所设大小限制值。',
            3 => '文件只有部分被上传。',
            4 => '文件没有被上传。',
            5 => '找不到临时文件夹。',
            6 => '文件写入失败。',
            7 => '文件上传出错。'
        ];
        if ($this->_inputFile['error'] != 0 && isset($log_list[$this->_inputFile['error']])) {
            $this->_putLog(false, $log_list[$this->_inputFile['error']]);
        }
    }

    /**
     * 上传路径及文件名处理
     * @return void
     */
    private function _setUploadEnvironment()
    {
        //创建上传文件路径
        if ($this->_path === '') {
            $this->_putLog(false, '没有设置文件上传路径参数。');
            return;
        }
        if (!is_dir($this->_path)) {
            if (!mkdir($this->_path, 0777, true)) {
                $this->_putLog(false, '创建上传目录失败。');
                return;
            }
        }
        //文件名处理
        $file_name = $this->_fileName === '' ? $this->_createRand(20) : $this->_fileName;
        $this->_fileName = $file_name . '.' . $this->_imageSuffix;
        $this->_newFile = $this->_path . $this->_fileName;
        //多尺寸文件名处理
        foreach ($this->_cImageSize as $k => $v) {
            $this->_cImageSize[$k]['file_name'] = $file_name . '_' . $v['width'] . '_' . $v['height'] . '.' . $this->_imageSuffix;
        }
    }

    /**
     * 上传文件验证
     * @return void
     */
    private function _verifyFile()
    {
        //验证文件类型
        $file_suffix_code = $this->_getFileSuffixCode($this->_inputFile['tmp_name']);
        if (!isset($this->_imageSuffixList[$file_suffix_code])) {
            $this->_putLog(false, '不是指定类型的图片。');
            return;
        }
        $this->_imageSuffix = $this->_imageSuffixList[$file_suffix_code];
        //验证文件大小
        if ($this->_cFileByte > 0 && $this->_inputFile['size'] > $this->_cFileByte) {
            $this->_putLog(false, '文件超出限制大小。');
            return;
        }
        //验证图片尺寸
        if (!empty($this->_cVerifyImageSize)) {
            $result = $this->_verifyImageSize();
            if (false === $result) {
                $this->_putLog(false, '图片SIZE限制条件不符。');
                return;
            }
        }
        //上传路径及文件名处理
        $this->_setUploadEnvironment();
    }

    /**
     * 针对IOS竖着拍照的相片进行旋转处理
     * @return void
     */
    private function _imageRotateCorrection()
    {
        //非JPG格式的图片不处理及传入了角度数据的不处理
        if ($this->_imageSuffix !== 'jpg' || isset($this->_cImageCutSize['rotate']) && $this->_cImageCutSize['rotate'] !== 0) {
            return;
        }
        //读取相片拍照的方向（除IOS竖着拍照外其他图片全部都是1）
        $exif = exif_read_data($this->_newFile, 'IFD0');
        $orientation = isset($exif['Orientation']) ? (int)$exif['Orientation'] : 1;
        if ($orientation === 1) {
            return;
        }
        //处理
        $this->_getImage(); //创建新的图像
        switch ($orientation) {
            case 3: //180度左旋转
                $this->_image = imagerotate($this->_image, 180, 0xFFFFFF);
                break;
            case 6: //90度右旋转
                $this->_image = imagerotate($this->_image, -90, 0xFFFFFF);
                break;
            case 8: //90度左旋转
                $this->_image = imagerotate($this->_image, 90, 0xFFFFFF);
                break;
            case 2: //水平翻转（不常见）
                imageflip($this->_image, IMG_FLIP_HORIZONTAL);
                break;
            case 4: //垂直翻转（不常见）
                imageflip($this->_image, IMG_FLIP_VERTICAL);
                break;
            case 5: //垂直翻转+90度右旋转（不常见）
                imageflip($this->_image, IMG_FLIP_VERTICAL);
                $this->_image = imagerotate($this->_image, -90, 0xFFFFFF);
                break;
            case 7: //水平翻转+90度右旋转（不常见）
                imageflip($this->_image, IMG_FLIP_HORIZONTAL);
                $this->_image = imagerotate($this->_image, -90, 0xFFFFFF);
                break;
            default:
                break;
        }
        $this->_saveImage($this->_image, $this->_newFile); //保存新的图像
    }

    /**
     * 生成指定大小的图片
     * @param string $file_name
     * @param int $width
     * @param int $height
     * @param string $type = 'new'
     * @return bool
     */
    private function _createNewImage(string $file_name, int $width, int $height, string $type = 'new') : bool
    {
        //计算图片缩放尺寸
        $size = $this->_getImageZoomSize($width, $height);
        if (empty($size)) {
            return false;
        }
        //如果是生成原图，原图大小与生成的大小一致则不操作
        if ($type === 'original') {
            if ($size['canvas_width'] === $size['pic_width'] && $size['canvas_height'] === $size['pic_height']) {
                return true;
            }
        }
        //创建图片
        $this->_getImage();
        $dst_img = imagecreatetruecolor($size['canvas_width'], $size['canvas_height']);
        if ($this->_imageSuffix === 'png') {
            imagefill($dst_img, 0, 0, imagecolorallocatealpha($dst_img, 0, 0, 0, 127));
            imagesavealpha($dst_img, true);
        } else {
            imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));
        }
        $result = imagecopyresampled($dst_img, $this->_image, $size['dst_x'], $size['dst_y'], 0, 0, $size['dst_width'], $size['dst_height'], $size['pic_width'], $size['pic_height']);
        if ($result) {
            $this->_saveImage($dst_img, $file_name);
        }
        imagedestroy($dst_img);
        return $result;
    }

    /**
     * 生成新裁切的尺寸图片
     * @param string $file_name
     * @param array $size
     * @return bool
     */
    private function _createNewCatImage(string $file_name, array $size) : bool
    {
        $this->_getImage();
        //旋转处理
        if ($this->_cImageCutSize['rotate'] != 0) {
            //这里的旋转不包含特殊处理（水平或垂直翻转）
            $this->_image = imagerotate($this->_image, -$this->_cImageCutSize['rotate'], imagecolorallocatealpha($this->_image, 0, 0, 0, 127));
            imagesavealpha($this->_image, true);
        }
        //创建图片
        $dst_img = imagecreatetruecolor($size['canvas_width'], $size['canvas_height']);
        if ($this->_imageSuffix === 'png') {
            imagefill($dst_img, 0, 0, imagecolorallocatealpha($dst_img, 0, 0, 0, 127));
            imagesavealpha($dst_img, true);
        } else {
            imagefill($dst_img, 0, 0, imagecolorallocate($dst_img, 255, 255, 255));
        }
        $result = imagecopyresampled($dst_img, $this->_image, $size['dst_x'], $size['dst_y'], $size['src_x'], $size['src_y'], $size['dst_w'], $size['dst_h'], $size['src_w'], $size['src_h']);
        if ($result) {
            $this->_saveImage($dst_img, $file_name);
        }
        imagedestroy($dst_img);
        return $result;
    }

    /**
     * 图片多尺寸生成或裁切处理
     * @return void
     */
    private function _createImage()
    {
        //如果没传裁切图片、多尺寸或原图尺寸参数则不处理
        if (empty($this->_cImageCutSize) && empty($this->_cImageSize) && empty($this->_cOriginalSize)) {
            return;
        }
        //如果裁切图片存在，则必须至少设置一项尺寸，同时不管原图尺寸是否设置都为失效
        if (!empty($this->_cImageCutSize) && !empty($this->_cImageSize)) {
            //计算裁切图片尺寸
            $size = $this->_getCutImageSize();
            //裁切设置的尺寸图
            foreach ($this->_cImageSize as $k => $v) {
                //计算目标图片的宽高和坐标
                $img_size = $size;
                $ratio = $this->_cImageCutSize['width'] / $v['width'];
                $img_size['dst_w'] /= $ratio;
                $img_size['dst_h'] /= $ratio;
                $img_size['dst_x'] /= $ratio;
                $img_size['dst_y'] /= $ratio;
                $img_size['canvas_width'] = $v['width'];
                $img_size['canvas_height'] = $v['height'];
                //生成新裁切的尺寸图片
                $result = $this->_createNewCatImage($this->_path . $v['file_name'], $img_size);
                if (false === $result) {
                    $this->_putLog(false, '创建带裁切图的新尺寸图失败。');
                }
            }
            return;
        }
        //在没有设置裁切图片下
        if (empty($this->_cImageCutSize)) {
            //如果设置了新的图片尺寸
            if (!empty($this->_cImageSize)) {
                foreach ($this->_cImageSize as $k => $v) {
                    //生成指定大小的图片
                    $result = $this->_createNewImage($this->_path . $v['file_name'], $v['width'], $v['height']);
                    if (false === $result) {
                        $this->_putLog(false, '创建新尺寸图失败。');
                    }
                }
            }
            //如果设置了原图尺寸则生效
            if (!empty($this->_cOriginalSize)) {
                //生成指定大小的图片
                $result = $this->_createNewImage($this->_newFile, $this->_cOriginalSize['width'], $this->_cOriginalSize['height'], 'original');
                if (false === $result) {
                    $this->_putLog(false, '设置原图尺寸失败。');
                }
            }
        }
    }

    /**
     * 参数：文件
     * @param array $file
     * @return void
     */
    public function setFile(array $file)
    {
        $this->_inputFile = $file;
    }

    /**
     * 参数：上传路径
     * @param string $root_path
     * @param string $path = ''
     * @return void
     */
    public function setPath(string $root_path, string $path = '')
    {
        if (substr($root_path, -1) !== '/') {
            $root_path .= '/';
        }
        if ($path !== '') {
            if (substr($path, -1) !== '/') {
                $path .= '/';
            }
            if (substr($path, 0, 1) === '/') {
                $path = substr($path, 1);
            }
            //替换$path变量中的定义变量值
            $path_var = [
                '{yyyy}' => date('Y'),
                '{mm}' => date('m'),
                '{dd}' => date('d')
            ];
            $path = strtr($path, $path_var);
            $this->_returnPath = $path;
        }
        $this->_path = $root_path . $path;
    }

    /**
     * 参数：文件大小（Kb）
     * @param int $kb
     * @return void
     */
    public function setVerifyKbByte(int $kb)
    {
        $this->_cFileByte = 1024 * $kb;
    }

    /**
     * 参数：文件大小（Mb）
     * @param int $mb
     * @return void
     */
    public function setVerifyMbByte(int $mb)
    {
        $this->_cFileByte = 1048576 * $mb;
    }

    /**
     * 参数：限制文件后缀
     * @param array $suffix
     * @return void
     */
    public function setVerifySuffix(array $suffix)
    {
        foreach ($this->_imageSuffixList as $k => $v) {
            if (!in_array($v, $suffix)) {
                unset($this->_imageSuffixList[$k]);
            }
        }
    }

    /**
     * 参数：文件名
     * @param string $file_name
     * @return void
     */
    public function setFileName(string $file_name)
    {
        //支持生成随机字符串变量"{rand:20}"
        preg_match("/{rand:(\d+)}/is", $file_name, $matches);
        if (count($matches) === 2) {
            $rand_string = $this->_createRand($matches[1]);
            $file_name = str_replace($matches[0], $rand_string, $file_name);
        }
        //支持生成当前时间变量
        $file_name = str_replace('{time}', date('His'), $file_name);
        $this->_fileName = $file_name;
    }

    /**
     * 参数：生成多尺寸图或针对原图尺寸（裁切图片时需与前台比例一致）
     * @param int $width
     * @param int $height
     * @param bool $is_original = false
     * @return void
     */
    public function setSize(int $width, int $height, bool $is_original = false)
    {
        if ($is_original) {
            //针对原图生成指定大小的尺寸
            $this->_cOriginalSize = [
                'width' => $width,
                'height' => $height
            ];
        } else {
            //生成多张不同尺寸图
            $this->_cImageSize[] = [
                'width' => $width,
                'height' => $height,
                'file_name' => ''
            ];
        }
    }

    /**
     * 参数：验证图片尺寸
     * @param string $width
     * @param string $height
     * @return void
     */
    public function setVerifyImageSize(string $width, string $height)
    {
        $symbol = [
            '<' => '>=',
            '>' => '<=',
            '<=' => '>',
            '>=' => '<',
            '=' => '!='
        ];
        $tmp_array = [
            'width' => $width,
            'height' => $height
        ];
        foreach ($symbol as $k => $v) {
            foreach ($tmp_array as $type => $string) {
                if (strpos($string, $k) !== false) {
                    $this->_cVerifyImageSize[$type] = [
                        'symbol' => $v,
                        'value' => str_replace($k, '', $string)
                    ];
                }
            }
        }
    }

    /**
     * 参数：裁切图尺寸
     * @param int $width
     * @param int $height
     * @param int $x
     * @param int $y
     * @param int $rotate
     * @return void
     */
    public function setCutSize(int $width, int $height, int $x, int $y, int $rotate)
    {
        $this->_cImageCutSize = [
            'width' => $width,
            'height' => $height,
            'x' => $x,
            'y' => $y,
            'rotate' => $rotate
        ];
    }

    /**
     * 参数：设置比例
     * @param float $ratio
     * @return void
     */
    public function setRatio(float $ratio)
    {
        if ($ratio > 1.2) {
            $this->_ratio = $ratio;
        }
    }

    /**
     * 上传
     * @return void
     */
    public function upload()
    {
        //判断上传文件是否错误
        $this->_fileError();
        if (false === $this->_result) {
            return;
        }
        //上传文件验证
        $this->_verifyFile();
        if (false === $this->_result) {
            return;
        }
        //移动图片（目标图片存在则替换）
        if (!move_uploaded_file($this->_inputFile['tmp_name'], $this->_newFile)) {
            $this->_putLog(false, '移动图片错误。');
            return;
        }
        //针对IOS竖着拍照的相片进行旋转处理
        $this->_imageRotateCorrection();
        //图片多尺寸生成或裁切处理
        $this->_createImage();
    }

    /**
     * 删除已上传的图片
     * @return bool
     */
    public function deleteAllImage() : bool
    {
        //获取所有图片的路径
        $image_list[] = $this->_newFile;
        foreach ($this->_cImageSize as $v) {
            $image_list[] = $this->_path . $v['file_name'];
        }
        //删除所有文件
        $result = true;
        foreach ($image_list as $file) {
            if (is_file($file)) {
                if (!unlink($file)) {
                    $result = false;
                }
            }
        }
        return $result;
    }

    /**
     * 获取上传状态
     * @return bool
     */
    public function getResult() : bool
    {
        return $this->_result;
    }

    /**
     * 获取上传日志
     * @return string
     */
    public function getLog() : string
    {
        return $this->_log;
    }

    /**
     * 获取上传后的文件名
     * @return array
     */
    public function getFileName() : array
    {
        if (false === $this->_result) {
            return [];
        }
        $file_name_list = [
            'original' => $this->_returnPath . $this->_fileName
        ];
        if (!empty($this->_cImageSize)) {
            foreach ($this->_cImageSize as $v) {
                $index = $v['width'] . '_' . $v['height'];
                $file_name_list[$index] = $this->_returnPath . $v['file_name'];
            }
        }
        return $file_name_list;
    }

}
