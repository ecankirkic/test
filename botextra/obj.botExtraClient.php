<?php
class botExtraClient {
    public $script         = false;
    public $version        = '4.0.4';
    public $allowedActions = array('checkClient', 'getCat', 'addToWebsite', 'createCategories', 'updateClient', 'getVideoSource', 'install','get','proxy');

    public function mysqlQuery($query, $type = 'query') {
        if (!$wpdb) {
            global $wpdb;
        }

        if (strstr($this->script, 'Wordpress')) {
            if ($type == 'query') {
                $result = $wpdb->query($query);
            } elseif ($type == 'num_rows') {
                $wpdb->get_results($query);
                $result = $wpdb->num_rows;
            } elseif ($type == 'result') {
                $result = $wpdb->get_var($query);
            }
        } else {
            if ($type == 'query') {
                $result = mysql_query($query);
            } elseif ($type == 'num_rows') {
                $result = @mysql_num_rows(@mysql_query($query));
            } elseif ($type == 'result') {
                $result = @mysql_result(@mysql_query($query), 0, 0);
            }
        }

        return $result;
    }

    public function install() {

        $sql = "CREATE TABLE IF NOT EXISTS `videosource` (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `partId` int(10) unsigned NOT NULL,
        `url` text NOT NULL,
        PRIMARY KEY (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

        if (strstr($this->script, "Wordpress")) {
            global $wpdb;
            $wpdb->query($sql);
        } else {
            mysql_query($sql);
        }

        if (mysql_errno()) {
            $hata = mysql_error();
            return $this->answer('HATA', 'Bot kurulurken su hata ile karsilasti :' . $hata, '', 001);
        }
        $this->answer('OK', 'Kurulum Tamamlandi', $data);
        return true;
    }

    public function checkClient() {

        if (!is_writable('botextra')) {
            if (!@chmod('botextra', 0777)) {
                $data['boxExtraClientFolderWritable'] = false;
            } else {
                $data['boxExtraClientFolderWritable'] = true;
            }
        } else {
            $data['boxExtraClientFolderWritable'] = true;
        }
        if ($this->uploadDir) {
            if (!is_writable($this->uploadDir)) {
                if (!@chmod($this->uploadDir, 0777)) {
                    $data['uploadDirWritable'] = false;
                } else {
                    $data['uploadDirWritable'] = true;
                }
            } else {
                $data['uploadDirWritable'] = true;
            }
        } else {
            $data['uploadDirWritable'] = true;
        }

        if (!$this->get('http://client.botextra.com')) {
            $data['serverOutsideAccess'] = false;
        } else {
            $data['serverOutsideAccess'] = true;
        }

        $data['clientVersion'] = $this->version;
        $data['uploadDir']     = $this->uploadDir;
        $data['script']        = $this->script;
        if (strstr($this->script, 'Wordpress')) {
            $data['theme']         = wp_get_theme()->get('Name');
            $postTypes = get_post_types(array('public' => true));

            foreach ($postTypes as $postType) {
                if ($postType == 'attachment') continue;
                if ($postType == 'page') continue;
                $postTypeObj = get_post_type_object($postType);

                $args = array(
                    'post_type' =>$postType,
                    'posts_per_page' => 1
                );
                $post = wp_get_recent_posts($args, OBJECT);
                $postId = $post[0];
                $postId = $postId->ID;
                $customFields = get_post_custom($postId);

                //$customFields = array_merge((array)get_post_meta($postId),(array)$customFields);

                //$data['wpData']['test'][$postId]['meta'] = get_post_meta($postId);
                //$data['wpData']['test'][$postId]['custom'] = get_post_custom($postId); 



                $notNeededCustoms[] = '_edit_lock';
                $notNeededCustoms[] = '_edit_last';
                $notNeededCustoms[] = '_thumbnail_id';


                foreach ((array)$customFields as $customKey => $v) {
                    if (in_array($customKey,$notNeededCustoms) !== FALSE) continue;
                    $value = $v[0];

                    if (@unserialize($value)) {
                        $value = unserialize($value);



                    }
                    if (is_array($value) or is_object($value)) {
                        $array = array();
                        if (method_exists($this,'replaceKeys')) {

                            $array = $this->replaceKeys($value,$customKey."->");
                        }
                        $return = array();
                        array_walk_recursive($array, function($a,$b) use (&$return) { $return[$b] = $a; });
                        foreach ($return as $returnKey => $returnValue) {
                            $customFieldsTotal[$returnKey] = $returnValue;
                        }
                    } else {
                        $customFieldsTotal[$customKey] = $value;
                    }


                }

                $output[$postType]['obj'] = $postTypeObj;
                $output[$postType]['post_id'] = $postId;

                $taxonomies = get_object_taxonomies($postType,OBJECT);
                foreach ($taxonomies as $k => $taxonomy) {
                    if ($k == 'post_tag') continue;
                    if (!$taxonomy->show_ui || !$taxonomy->public) continue;
                    $output[$postType]['taxonomy'][$k] = $taxonomy;
                }

                //$output[$postType]['taxonomy'] = get_object_taxonomies($postType,OBJECT);
            }

            $data['wpData']['customFields'] = $customFieldsTotal;
            $data['wpData']['customPostTypes'] = $output;
        }
        $this->answer('OK', $this->script, $data);
    }

    function replaceKeys($array, $prefix = '') {
        $result = array();
        foreach($array as $key=>$value) {
            if(is_array($value)) {
                $result = $result + $this->replaceKeys($value, $prefix . $key . '->');
            }
            else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    public function answer($code, $message, $data = '', $errorNo = '') {
        $answer['code']    = $code;
        $answer['message'] = $message;
        $answer['data']    = $data;
        $answer['errorNo'] = $errorNo;
        echo "[answer]" . json_encode($answer) . "[/answer]";
    }

    public function controlAction($action) {

        if (array_search($action, $this->allowedActions) !== false) {
            if ($action !== 'checkClient' and $action !== 'updateClient' and $action !== 'getVideoSource' and $action !== 'proxy') {
                if ($this->isAllowedToSendCommand()) {
                    return true;
                } else {
                    $this->answer('HATA', 'Gecersiz Komut Gondericisi', '', 'ERR005');
                    die('');
                }
            }
            return true;
        } else {
            $this->answer('HATA', 'Gecersiz Komut', '', 'ERR004');
            die('');
        }
    }

    public function getParamaters($action) {
        global $safePost;
        $params = json_decode(base64_decode($safePost['content']), 1);
        addSlashesArray($params);
        return $params;
    }

    public function isAllowedToSendCommand() {
        if (@$_SERVER['HTTP_X_REAL_IP'] == '167.114.0.98' or @$_SERVER['REMOTE_ADDR'] == '167.114.0.98' or @$_SERVER['HTTP_X_REAL_IP'] == '167.114.0.226' or @$_SERVER['REMOTE_ADDR'] == '167.114.0.226' or @$_SERVER['HTTP_X_REAL_IP'] == '142.4.218.216' or @$_SERVER['REMOTE_ADDR'] == '142.4.218.216') {
            return true;
        }

        if ($this->checkToken($token)) {
            return true;
        }

        return false;
    }

    public function get($url='') {
        if (!$url) $url = urldecode($_GET['url']);
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            $result = curl_exec($ch);
            curl_close($ch);
        } else {
            $result = @file_get_contents($url);
        }
        return $result;
    }

    public function checkToken() {
        global $contentId;
        $token = $_GET['token'];
        $url   = "http://client.botextra.com/checkToken/check/$token/$contentId";
        if ($result = $this->get($url)) {
            if ($result == "OK") {
                return true;
            }

        }
        return false;
    }

    public function seoString($string) {
        $find    = array("ı", "ö", "ü", "ğ", "ş", "ç", "İ", "Ö", "Ü", "Ğ", "Ş", "Ç", ".", "  ", " ");
        $replace = array("i", "o", "u", "g", "s", "c", "i", "O", "U", "G", "S", "C", "-", " ", "-");

        $string = preg_replace("'<[\\/\\!]*?[^<>]*?>'si", "", $string);
        $string = str_replace($find, $replace, $string);
        $string = str_replace("--", "-", $string);
        $string = strtolower($string);
        // if (function_exists('ereg_replace')) {
        //     $string = ereg_replace("[^A-Za-z0-9-]", "", $string);
        // } else {
        $string = preg_replace('~[^a-zA-Z0-9]+~', '-', $string);
        // }
        $string = ltrim($string, '-');
        $string = rtrim($string, '-');
        return $string;
    }

    public function download($url, $name = '', $type = 'image') {

        $ext = $this->getExt($url, $type);

        if (!$ext) {
            return false;
        }

        if (!$name) {
            $name = time();
        }

        if ($type == 'image') {
            $allowedImageExtension = array('.jpg', '.jpeg', '.gif', '.png');
            if (!array_search($ext, $allowedImageExtension)) {
                $ext = '.jpg';
            }

        }

        if (isset($this->uploadUrl)) {
            $this->uploadUrl = rtrim($this->uploadUrl, '/') . '/';
        }

        $this->uploadDir = rtrim($this->uploadDir, '/');

        if (!file_exists($this->uploadDir)) {
            @mkdir($this->uploadDir, 0777, true);
        }

        $fileName = $name . $ext;
        $path     = $this->uploadDir . '/' . $fileName;

        if (function_exists('curl_init')) {
            $fp = fopen($path, 'w+');
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 9.0; Windows NT 5.1)');
            @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch,CURLOPT_COOKIEJAR,  'cookies.txt');
            curl_setopt($ch,CURLOPT_COOKIEFILE, 'cookies.txt');
            curl_exec($ch);
            curl_close($ch);
            fclose($fp);
            if (filesize($path) > 0) {
                if (isset($this->uploadUrl)) {
                    return $this->uploadUrl . $fileName;
                }
                return $path;
            }

        } else {
            if (copy($url, $path)) {
                if (isset($this->uploadUrl)) {
                    return $this->uploadUrl . $fileName;
                }
                return $path;
            }
        }

        return false;
    }

    public function insertData($table, $posts) {

        $sqlSet = '';
        foreach ($posts as $key => $value) {
            $sqlSet .= "$key='" . mysql_real_escape_string($value) . "',";
        }
        $sqlSet = rtrim($sqlSet, ',');
        $sql    = "INSERT INTO $table SET $sqlSet";
        mysql_query($sql);
        return mysql_insert_id();
    }

    public function getData($query) {
        return mysql_fetch_object(mysql_query($query));
    }

    public function getExt($url = '', $type = 'image') {

        $x = explode('.', $url);
        if (count($x) === 1) {
            if ($type != 'image') {
                return '';
            }

            return '.jpg';
        }
        $ext = strtolower(end($x));
        $ext = current(explode('?', $ext));
        return '.' . $ext;
    }


    function proxy() {
        $params = json_decode(base64_decode($_GET['options']), 1);
        $ch = curl_init();
        curl_setopt_array($ch,$params);
        echo curl_exec($ch);
        die();
    }


    public function getVideoSource() {
        error_reporting(0);
        @ini_set('display_errors', 0);
        if (!is_numeric($_GET['id']) || $_GET['id'] < 0) {
            die();
        }

        $sql = "SELECT url FROM videosource WHERE partId=$_GET[id]";
        if ($this->mysqlQuery($sql, "num_rows")) {
            $videoUrl = $this->mysqlQuery($sql, 'result');
            if ($_GET['debug']) {
                echo 'Localden aldı<br>';
            }
        } else {
            $videoUrl = $this->get('http://client.botextra.com/getVideoSource/?id=' . $_GET['id']);
            if ($videoUrl) {
                $this->mysqlQuery("INSERT INTO videosource SET partId=$_GET[id], url='$videoUrl'");
            }
            if ($_GET['debug']) {
                echo 'Serverdan aldı<br>';
            }
        }

        $size = $this->remoteFileSize($videoUrl);
        if ($size < 1000000) {
            $videoUrl = $this->get('http://client.botextra.com/getVideoSource/?id=' . $_GET['id']);
            if ($videoUrl) {
                $this->mysqlQuery("UPDATE videosource SET url='$videoUrl' WHERE partId=$_GET[id]");
            }
            if ($_GET['debug']) {
                echo 'Mp4 açılmadığı için tekrar serverdan aldı<br>';
            }
        }

        if ($_GET['debug']) {
            die($videoUrl);
        }
        $videoUrl = trim(preg_replace('/\s+/', '', $videoUrl));
        header("Location: $videoUrl");
        return false;
    }

    public function remoteFileSize($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_exec($ch);
        $filesize = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);
        if ($filesize) {
            return $filesize;
        } else {
            if (isset($curlInfo['content_type']) && $curlInfo['content_type'] == 'video/mp4') {
                return 1000000;
            }
            if (isset($curlInfo['url']) && strstr($curlInfo['url'],'googlevideo.com')) {
                return 1000000;
            }
        }
    }

    public function updateClient() {
        include 'obj.updater.php';
        $objUpdater          = new updater();
        $objUpdater->version = $this->version;
        $objUpdater->script  = $this->script;
        $objUpdater->update();
    }

    public function siteUrl($route = '') {
        return 'http://' . @$_SERVER['SERVER_NAME'] . '/' . $route;
    }

}

if (!function_exists('pre')) {
    function pre($arr) {
        echo "<pre>";
        print_r($arr);
        echo "</pre>";
    }
}

if (!function_exists('addSlashesArray')) {
	function addSlashesArray(&$arr_r) {
		if (isset($arr_r) && is_array($arr_r)) {
			foreach ($arr_r as &$val) {
				is_array($val) ? addSlashesArray($val) : $val = addslashes(htmlspecialchars_decode($val));
			}

			unset($val);
		}
	}
}
?>
