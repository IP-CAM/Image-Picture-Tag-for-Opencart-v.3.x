<?php
class ControllerExtensionModulePicture extends Controller {
    private $formats = [ // mime-types
	    // jd todo Підтримка png / jp2 / webp / jpg
//        'webp' =>   'image/webp', // jd todo Підтримка webp
        'jpg'  =>   'image/jpeg',
//        'png',
    ];
    private $width_set = [
    	// jd todo Зараз ширини взяті із масиву мобільних емуляторів інструиентів розробника Chrome, тут можна підібрати масив ширин
	    360,
		375,
		415,
		540,
		650,
		768,
		823,
		1024,
		1200,
		1920,
	    2000
//        4000 // max image size
    ];


    public function index($data) {
        return '';
    }

	public function create_srcset($data) {

		$img =
			('' != $data['image']
				&& file_exists(DIR_IMAGE . $data['image'])
			) ?
				$data['image'] : 'placeholder.png';
		$info = pathinfo($img);
		$info['dirname'] = !empty($info['dirname'])? $info['dirname'] : '';
		$img_path = $info['dirname'] . '/' . $info['filename'];
		$data['img_path'] = $img_path;

		$alt = !empty($data['alt']) ? $data['alt'] : "";
		$title = !empty($data['title']) ? $data['title'] : "";
		$class = !empty($data['class']) ? $data['class'] : "";
		$transform = 'w'; //$data['transform'];

		$srcset = [];
/* Кешування. Спрацьовує неправильно, бо $filename генерується швидше
		$filename = DIR_IMAGE . "cache/{$img_path}-{$data['width']}x{$data['height']}.{$info['extension']}";
		if(!file_exists($filename)) {
*/
			$requested_width = $data['width'];
			$requested_height = $data['height'];


			$requested_scale = $source_scale = $requested_height / $requested_width;


			$src_img = new Image(DIR_IMAGE . $data['image']);
			$src_width = $src_img->getWidth();

			// jd todo Перевірити існування забражень типу image_name-_width_w.ext
			$source_img_set = $this->getImgSet($data['image']);
			if (count($source_img_set) > 1) {
//			unset($source_img_set[0]); // Видалення оригіналу, він у нас в $data['image']
				$source_img_width = array_key_first($source_img_set);
				$source_img = new Image(DIR_IMAGE . $source_img_set[$source_img_width] . '.' . $info['extension']);
				$requested_height = $source_img_height = $source_img->getHeight();
				$img_path = $source_img_set[$source_img_width];
			} else {
				$source_img_width = $src_width;
			}

			$this->load->model('tool/image');

			foreach ($this->formats as $ext => $mime) {
				// Створення різноформатних сорсів, наразі відкладаю 30-03-2021
				/* if (!file_exists(DIR_IMAGE . $img_path . '.' . $ext)) {
					$src = new Image(DIR_IMAGE . $img);
					$src->save(DIR_IMAGE . $img_path . '.' . $ext);
				} */
				foreach ($this->width_set as $responsive_width) {
					// Не генерувати зображення більші ніж оригінал
//				if($src_width < $responsive_width) {
//					continue;
//				}


					if ($source_img_width < $responsive_width && count($source_img_set) > 1) {

						while ($source_img_width < $responsive_width) {
							unset($source_img_set[$source_img_width]);
							if (!empty($source_img_set)) {
								$source_img_width = array_key_first($source_img_set);
								$source_img = new Image(DIR_IMAGE . $source_img_set[$source_img_width] . '.' . $info['extension']);
								$source_scale = $source_img->getHeight() / $source_img_width;
							} else {
								$requested_scale = $responsive_width / $data['width'];
								$source_img_width = $data['width'] * $requested_scale;
								$source_scale = $data['height'] / $source_img_width;
							}

						}

						$requested_height = $source_scale * $responsive_width;
						$img_path = !empty($source_img_set[$source_img_width]) ?
							$source_img_set[$source_img_width]
							: $data['img_path'];
					} elseif ($source_img_width < $responsive_width) {
						$requested_scale = $responsive_width / $data['width'];
						$responsive_width = $data['width'] * $requested_scale;
						$requested_height = $data['height'] * $requested_scale;

					} elseif ($source_img_width == $responsive_width) {
						$requested_height = $source_img_height;
					} elseif ($source_img_width > $responsive_width && !empty($source_img_set[$source_img_width])) {
						$requested_height = $source_scale * $responsive_width;
						$img_path = $source_img_set[$source_img_width];
					} elseif ($source_img_width > $responsive_width) {
						$requested_height = $responsive_width * $requested_scale;
					}


					// Генерування зображень
					if ($responsive_width < $requested_width) {
						$srcset['srcset'][$mime][$responsive_width . 'w'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
							$this->model_tool_image->resize( // $filename, $width, $height, $drop_sizes = false
								$img_path . '.' . $ext,
								$responsive_width,
								$requested_height,
								true
							)
						);
					} else {
						$srcset['srcset'][$mime][$responsive_width . 'w'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
							$this->model_tool_image->resize( // $filename, $width, $height, $drop_sizes = false
								$img_path . '.' . $ext,
								$responsive_width,
								$requested_height,
								true
							)
						);
						$srcset['srcset'][$mime][(2 * $responsive_width) . 'w'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
							$this->model_tool_image->resize( // $filename, $width, $height, $drop_sizes = false
								$img_path . '.' . $ext,
								2 * $responsive_width,
								2 * $requested_height,
								true
							)
						);
						continue;
					}


				}
			}
/* Кешування. Див. вище
		}
		else {
			foreach ($this->formats as $ext => $mime) {

				$images = $this->scanCachedImages($img_path, $ext);
				foreach ($this->width_set as $responsive_width) {
					if (!empty($images[$responsive_width])){
						$srcset['srcset'][$mime][$responsive_width . 'w'] = $images[$responsive_width];
					}
				}

			}
		}
*/
		$srcset['src'] = str_replace([HTTPS_SERVER, '//'], ['', '/'], $this->model_tool_image->resize( // $filename, $width, $height, $type = ''
			$img,
			$data['width'],
			$data['height'],
			false
		));
		$srcset['alt']   = $alt? $alt : $title;
		$srcset['title'] = $title;
		$srcset['class'] = $class;

//        echo "<pre>" . print_r(['picture' => $srcset], 1) . "</pre>"; die();
		return $this->load->view('extension/module/picture', ['picture' => $srcset]);
	}
	public function getImgSet($image) {
		$info = pathinfo($image);
		$info['dirname'] = !empty($info['dirname'])? $info['dirname'] : '';
		$basename = $info['dirname'] . '/' . $info['filename'];

		$offset = strlen(DIR_IMAGE);
		$length = strlen($info['extension']) + 1;
		$pattern = "/-(\d+)w\." . $info['extension'] . "/";

		$images = glob(DIR_IMAGE . $basename . '*.*');

		$srcset = [];
//		$srcset[] = $basename . '.' . $info['extension'];

		foreach ($images as $image) {
			preg_match($pattern, $image, $matches);
			if(!empty($matches[1])) {
				$width = (int)$matches[1];
			}
			else {
				$img = new Image ($image);
				$width = $img->getWidth();
			}

			$srcset[$width] = substr($image, $offset, - $length);
		}

		ksort($srcset);

		return $srcset;
	}
	public function scanCachedImages($img_path, $ext) {
		$images = glob(DIR_IMAGE . 'cache/' .$img_path . '-*.' . $ext);
		$result = [];
		foreach ($images as $image) {
			$slash_path = str_replace('/', '\/', $img_path);
			preg_match("/(.*)({$slash_path})-(\d+)x(\d+)\.{$ext}/", $image, $matches);
			$width = $matches[3];
			$height = $matches[4];
			$result[(int)$width] = 'image/cache/' . $img_path . '-' . "{$width}x{$height}.{$ext}";
		}
		return $result;
	}
}