<?php
class ModelToolPicture extends Model {

	/**
	 * @param string $image
	 * @param int $req_img_width
	 * @param int $req_img_height
	 * @param array $params
	 */
	public function create(string $image, int $req_img_width, int $req_img_height, array $params = []) {
		$src_img_path =
			('' != $image
				&& file_exists(DIR_IMAGE . $image)
			) ?
				$image : 'placeholder.png';
		if(!$this->config->get('module_picture_width_set')) {
			$this->config->load('extra/picture');
		}
		$src_info = pathinfo($src_img_path);
		$src_info['dirname'] = !empty($src_info['dirname'])? $src_info['dirname'] : '';
		$src_path = $src_info['dirname'] . '/' . $src_info['filename']; // path without extension
		$data['src_path'] = $src_path;

		$transform = $this->config->get('module_picture_transform'); //$data['transform'];

		$srcset = [];
		/* Кешування. Спрацьовує неправильно, бо $filename генерується швидше
				$filename = DIR_IMAGE . "cache/{$img_path}-{$data['width']}x{$data['height']}.{$info['extension']}";
				if(!file_exists($filename)) {
		*/

		if(!empty($params['ignore_source_scale']) && $this->config->get('module_picture_allow_ignore_source_scale')) {
			$ignore_source_scale = true;
		}
		else {
			$ignore_source_scale = false;
			$req_img_scale = $src_img_scale = $req_img_height / $req_img_width;
		}

		// Перевірити існування забражень типу image_name-_width_w.ext
		// Формуємо список в порядку зростання ширини, включно із оригіналом
		$img_src_set = $this->getImgSet($image);
		end($img_src_set);
		$max_responsive_width = key($img_src_set);

		// Починаємо з найменшої ширини зображення
//		$src_img_width = array_key_first($src_img_set);
//		$src_img = new Image(DIR_IMAGE . $src_img_set[$src_img_width] . '.' . $src_info['extension']);
//		$req_height = $src_img_height = $src_img->getHeight();
//		$img_path = $src_img_set[$src_img_width];

		$this->load->model('tool/image');

		foreach ($this->config->get('module_picture_formats') as $ext => $mime) {

			reset($img_src_set);
			// Створення різноформатних сорсів, наразі відкладаю 30-03-2021
			/* if (!file_exists(DIR_IMAGE . $img_path . '.' . $ext)) {
				$src = new Image(DIR_IMAGE . $img);
				$src->save(DIR_IMAGE . $img_path . '.' . $ext);
			} */
			foreach ($this->config->get('module_picture_width_set') as $resp_width) {
				// Не генерувати зображення більші ніж максимальне значення в srcset оригінал
				if($max_responsive_width < $resp_width) {
					continue;
				}

				$resp_src_img_width = key($img_src_set);
				if ($resp_src_img_width < $resp_width && ($resp_src_img_path = next($img_src_set)) !== false) {

					$resp_src_img_width = key($img_src_set);
					while ($resp_src_img_width < $resp_width || $resp_src_img_path === false) {
						$resp_src_img_path = next($img_src_set);
						$resp_src_img_width = key($img_src_set);
					}
				}
				elseif ($resp_src_img_width < $resp_width) {
					$resp_src_img_path = $src_img_path;
				}
				else {
					$resp_src_img_path = current($img_src_set);
				}


				$resp_height = $ignore_source_scale? $resp_width * $req_img_scale : false;
				if(!$resp_height) {
					$resp_src_img = new Image(DIR_IMAGE . $resp_src_img_path . '.' . $src_info['extension']);
					$resp_height = $resp_width * $resp_src_img->getHeight() / $resp_src_img->getWidth();
				}

				// Генерування зображень
				if ($resp_width < $req_img_width) {
					$srcset['srcset'][$mime][$resp_width . 'px'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
						$this->model_tool_image->resize( // $filename, $width, $height
							$resp_src_img_path . '.' . $ext,
							$resp_width,
							$resp_height
						)
					);
				}
				else {
					$srcset['srcset'][$mime][$resp_width . 'px'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
						$this->model_tool_image->resize( // $filename, $width, $height
							$resp_src_img_path . '.' . $ext,
							$resp_width,
							$resp_height
						)
					);
					$srcset['srcset'][$mime][(2 * $resp_width) . 'px'] = str_replace([HTTPS_SERVER, '//'], ['', '/'],
						$this->model_tool_image->resize( // $filename, $width, $height
							$resp_src_img_path . '.' . $ext,
							2 * $resp_width,
							2 * $resp_height
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
								$srcset['srcset'][$mime][$responsive_width . 'px'] = $images[$responsive_width];
							}
						}

					}
				}
		*/
		$srcset['src'] = str_replace([HTTPS_SERVER, '//'], ['', '/'], $this->model_tool_image->resize( // $filename, $width, $height, $type = ''
			$src_img_path,
			$req_img_width,
			$req_img_height,
			false
		));
		$srcset['title'] = !empty($params['title'])? $params['title'] : '';
		$srcset['alt']   = !empty($params['alt'])? $params['alt'] : $srcset['title'];

		$srcset['class'] = !empty($params['class'])? $params['class'] : "";

//        echo "<pre>" . print_r(['picture' => $srcset], 1) . "</pre>"; die();
		return $this->load->view('extension/module/picture', ['picture' => $srcset]);
	}

	/**
	 * @param $image
	 * @return array
	 *
	 * Шукає список зображень до заданого із приставкою до імені "-(\d+)w"
	 */
	public function getImgSet($image) {
		$info = pathinfo($image);
		$info['dirname'] = !empty($info['dirname'])? $info['dirname'] : '';
		$basename = $info['dirname'] . '/' . $info['filename'];
		$src_img = new Image(DIR_IMAGE . $image);
		$src_img_width = $src_img->getWidth();

		$offset = strlen(DIR_IMAGE);
		$length = strlen($info['extension']) + 1;
		$pattern = "/-(\d+)w\." . $info['extension'] . "/";
		$glob_pattern = "-[0-9]*w." . $info['extension'];

		$images = glob(DIR_IMAGE . $basename . $glob_pattern);

		$srcset = [];
		$srcset[$src_img_width] = $basename;

		foreach ($images as $image) {
			preg_match($pattern, $image, $matches);
			if(!empty($matches[1])) {
				$width = (int)$matches[1];
				$srcset[$width] = substr($image, $offset, - $length);
			}
		}

		ksort($srcset);
		return $srcset;
	}
}