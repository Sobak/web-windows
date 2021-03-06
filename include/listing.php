<?php

function bytes2string($size, $precision = 2) {
	$sizes = array('YB', 'ZB', 'EB', 'PB', 'TB', 'GB', 'MB', 'kB', 'B');
	$total = count($sizes);

	while($total-- && $size > 1024) $size /= 1024;

	return round($size, $precision).$sizes[$total];
}


function processSha1Sums($path)
{
	if (!file_exists($snaps_dir . 'sha1sum.txt')) {
		return array();
	}
	$sha1sums = file($snaps_dir . 'sha1sum.txt');
	$res = array();
	foreach ($sha1sums as $sha1){
		list($sha1, $file) = explode('  ', $sha1);
		$file = str_replace(array("\r","\n", $snaps_dir), array('','', ''), $file);
		$res[strtolower(basename($file))] = $sha1;
	}
	return $res;
}


function processSha256Sums($path)
{
	if (!file_exists($snaps_dir . 'sha256sum.txt')) {
		return array();
	}
	$sha256sums = file($snaps_dir . 'sha256sum.txt');
	$res = array();
	foreach ($sha256sums as $sha256){
		list($sha256, $file) = preg_split("/\s+\*?/", $sha256);
		$file = str_replace(array("\r","\n", $snaps_dir), array('','', ''), $file);
		$res[strtolower(basename($file))] = $sha256;
	}
	return $res;
}


function parse_file_name($v)
{
	$v = str_replace(array('-Win32', '.zip'), array('', ''), $v);

	$elms = explode('-', $v);
	if (is_numeric($elms[2]) || $elms[2] == 'dev') {
		$version = $elms[1] . '-' . $elms[2];
		$nts = $elms[3] == 'nts' ? 'nts' : false;
		if ($nts) {
			$vc = $elms[4];
			$arch = $elms[5];
		} else {
			$vc = $elms[3];
			$arch = $elms[4];
		}
	} elseif ($elms[2] == 'nts') {
		$nts = 'nts';
		$version = $elms[1];
		$vc = $elms[3];
		$arch = $elms[4];
	} else {
		$nts = false;
		$version = $elms[1];
		$vc = $elms[2];
		$arch = $elms[3];
	}
	if (is_numeric($vc)) {
		$vc = 'VC6';
		$arch = 'x86';
	}
	$t = count($elms) - 1;
	$ts = is_numeric($elms[$t]) ? $elms[$t] : false;

	return array(
			'version'  => $version,
			'version_short'  => substr($version, 0, 3),
			'nts'      => $nts,
			'vc'       => $vc,
			'arch'     => $arch,
			'ts'       => $ts
			);
}

function generate_listing($path, $snaps = false) {
	if (file_exists($path . '/cache.info')) {
		include $path . '/cache.info';
		return $releases;
	}

	$old_cwd = getcwd();
	chdir($path);

	$versions = glob('php-[567].*[0-9]-latest.zip');
	if (empty($versions)) {
		$versions = glob('php-[567].*[0-9].zip');
	}

	$releases = array();
	$sha1sums = processSha1Sums($path);
	$sha256sums = processSha256Sums($path);
	foreach ($versions as $file) {
		if (0&& !$snap && strpos($file, '5.2.9-2')) {
			continue;
		}
		$file_ori = $file;
		if ($snaps) {
			$file = readlink($file);
		}
		$datetime_str = substr($file, strlen($file) - 16, 12);
		if (!preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})([0-9]{2})([0-9]{2})/", $datetime_str, $m)) {
			$mtime = date('Y-M-d H:i:s', filemtime($file));
		} else {
			$snap_time = date_create();
			$snap_time->setDate($m[1], $m[2], $m[3]);
			$snap_time->setTime( $m[4], $m[5], 0);
			$mtime = date_format($snap_time, 'Y-M-d H:i:s');
			$snap_time_suffix = $m[1] . $m[2] . $m[3] . $m[4] . $m[5];
		}


		$elms = parse_file_name(basename($file));
		$key = ($elms['nts'] ? 'nts-' : 'ts-') . $elms['vc'] . '-' . $elms['arch'];
		$version_short = $elms['version_short'];
		if (!isset($releases['version'])) {
			$releases[$version_short]['version'] = $elms['version'];
		}
		$releases[$version_short][$key]['mtime'] = $mtime;
		$releases[$version_short][$key]['zip'] = array(
				'path' => $file_ori,
				'size' => bytes2string(filesize($file_ori)),
				'sha1' => $sha1sums[strtolower($file_ori)],
				'sha256' => $sha256sums[strtolower($file_ori)]
				);
		$compile = $configure = $buildconf = false;
		if ($snaps) {
			$debug_pack = 'php-debug-pack-' . $elms['version_short'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-win32' . ($elms['ts'] ? '-' . $elms['vc'] . '-' . $elms['arch'] . '-latest' : '') . '.zip';
			$installer =  'php-' . $elms['version_short'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-win32' . ($elms['ts'] ? '-' . $elms['vc'] . '-' . $elms['arch'] . '-latest' : '') . '.msi';
			$testpack = 'php-test-pack-' . $elms['version_short'] . '-latest.zip';
			$source     = 'php-' . $elms['version_short'] . '/php-' . $elms['version_short'] . '-src-latest.zip';
			$configure  = 'configure-' . $elms['version_short'] . '-' . $elms['vc'] . '-' . $elms['arch'] . '-' . ($elms['nts'] ? $elms['nts'] . '-' : '') .  $snap_time_suffix . '.log';
			$compile    = 'compile-' . $elms['version_short'] . '-' . $elms['vc'] . '-' . $elms['arch'] . '-' . ($elms['nts'] ? $elms['nts'] . '-' : '') . $snap_time_suffix . '.log';
			$buildconf  = 'buildconf-'. $elms['version_short'] . '-' . $elms['vc'] . '-' . $elms['arch'] . '-' . ($elms['nts'] ? $elms['nts'] . '-' : '') . $snap_time_suffix . '.log'; 
		} elseif ($version_short != '5.2') {
			$debug_pack = 'php-debug-pack-' . $elms['version'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-Win32-' . $elms['vc'] . '-' . $elms['arch'] . ($elms['ts'] ? '-' . $elms['ts'] : '') . '.zip';
			$installer =  'php-' . $elms['version'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-Win32-' . $elms['vc'] . '-' . $elms['arch'] . ($elms['ts'] ? '-' . $elms['ts'] : '') . '.msi';
			$source = 'php-' . $elms['version'] . '-src.zip';
		} else {
			$debug_pack = 'php-debug-pack-' . $elms['version'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-Win32-' . $elms['vc'] . '-' . $elms['arch'] . '.zip';
			$installer =  'php-' . $elms['version'] . ($elms['nts'] ? '-' . $elms['nts'] : '') . '-Win32-' .   $elms['vc'] . '-' . $elms['arch'] . '.msi';
			$source = 'php-' . $elms['version'] . '-src.zip';
		}
		if (file_exists($source)) {
			$releases[$version_short]['source'] = array(
					'path' => $source,
					'size' => bytes2string(filesize($source))
					);
		}
		if (file_exists($debug_pack)) {
			$releases[$version_short][$key]['debug_pack'] = array(
					'size' => bytes2string(filesize($debug_pack)),
					'path' => $debug_pack,
					'sha1' => $sha1sums[strtolower($debug_pack)],
					'sha256' => $sha256sums[strtolower($debug_pack)]
						);
		}		
		if (file_exists($installer)) {
			$releases[$version_short][$key]['installer'] = array(
					'size' => bytes2string(filesize($installer)),
					'path' => $installer,
					'sha1' => $sha1sums[strtolower($installer)],
					'sha256' => $sha256sums[strtolower($installer)]
						);
		}
		if (file_exists($testpack)) {
			$releases[$version_short]['test_pack'] = array(
					'size' => bytes2string(filesize($testpack)),
					'path' => $testpack,
					'sha1' => $sha1sums[strtolower($testpack)],
					'sha256' => $sha256sums[strtolower($testpack)]
						);
		}


		if ($snaps) {
			if ($buildconf) {
				$releases[$version_short][$key]['buildconf'] = $buildconf;
			}
			if ($compile) {
				$releases[$version_short][$key]['compile'] = $compile;
			}
			if ($configure) {
				$releases[$version_short][$key]['configure'] = $configure;
			}
		} else {
			if ($version_short == '5.2' && strpos($key, 'nts') !== false) {
				$releases[$version_short][$key]['webpi_installer'] = 'http://www.microsoft.com/web/gallery/install.aspx?appsxml=www.microsoft.com%2Fweb%2Fwebpi%2F2.0%2FWebProductList.xml%3Bwww.microsoft.com%2Fweb%2Fwebpi%2F2.0%2FWebProductList.xml%3Bwww.microsoft.com%2Fweb%2Fwebpi%2F2.0%2FWebProductList.xml&appid=201%3B202%3B203';
			}
		}
	}

	$cache_content = '<?php $releases = ' . var_export($releases, true) . ';';
	$tmp_name = tempnam('.', '_cachinfo');
	file_put_contents($tmp_name, $cache_content);
	rename($tmp_name, 'cache.info');
	chdir($old_cwd);
	return $releases;
}

/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * End:
 * vim600: sw=4 ts=4 fdm=marker
 * vim<600: sw=4 ts=4
 */
