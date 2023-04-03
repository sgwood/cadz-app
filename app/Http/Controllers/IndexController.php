<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;



class IndexController extends Controller
{

    protected array $webInfo = [
        'companyId' => '100070',
        'domain' => '',
        'domainScheme' => '',

        'cacheBug' => '0',
        'cacheTTL' => '300',

        'cacheReplaceA' => '1',
        'mtimeTTL' => '2592000',
        'throttle' => '30',
        'hasMobile' => '1',

        'optimizeDomain' => '180.76.55.80',
        'sourceDomain' => 'xiaohucloud.com',
        'imgDomain' => 'img-xhyftp.xiaohucloud.cn',
        'env' => ' ',
        'cacheDir' => 'cache',
        'cacheSuffix' => 'html',
        'uri' => '',
        'diyDomain' => 'diy-xhyftp.xiaohucloud.cn',
        'cosDomain' => 'cos-xhyftp.xiaohucloud.cn',
        'isMobile' => false,
        'sftp' => false,
        'debug' => true,
        'optimizeUrl' => '',
        'sourceUrl' => '',
        'cacheFlag' => '',
        'savePath' => '',
        'path' => '',
        'query' => '',
        'isSpider' => false,
    ];

    public function index(): View
    {
        try {
            $this->webInfo['uri']=$_SERVER['REQUEST_URI'];
            $this->main($this->webInfo);
        } catch (Exception $e) {
            header('HTTP/1.1 404 Not Found');
        } catch (Error $e) {
            header('HTTP/1.1 404 Not Found');
        }
        return view('user.profile');
    }


    function main(&$webInfo)
    {
        $this->init($webInfo);
        $htmlContent ='';
        if (file_exists($webInfo['savePath'])) {
            $htmlContent = file_get_contents($webInfo['savePath']);
        }

        $mtime = time() - @filemtime($webInfo['savePath']);
        $timeout = 2;
        if ($mtime <= $webInfo['cacheTTL'] * 2 - 10) {
            $timeout = 4;
        }

        if ((isset($htmlContent) && $webInfo['isSpider'] == false && $webInfo['throttle'] > 0 && $mtime >= $webInfo['cacheTTL']) || !isset($htmlContent) || ($webInfo['isSpider'] == false && $mtime >= $webInfo['mtimeTTL'])) {
            $throttle = __DIR__ . DIRECTORY_SEPARATOR . '.throttle';
            @file_put_contents($throttle, $webInfo['throttle'] - 1);
            switch (trim($webInfo['env'])) {
                case 'local':
                    break;
                case 'source':
                    $trimHtmlContent = trim($htmlContent);
                    if (isset($htmlContent) && !empty($trimHtmlContent)) {
                        list($content, $code) = $this->getContent($webInfo['sourceUrl'], $timeout);
                    } else {
                        list($content, $code) = $this->getContent($webInfo['sourceUrl'], 15);
                    }
                    $trimContent = trim($content);
                    if ($code == 200 && !empty($trimContent)) {
                        $htmlContent = $content;
                    }
                    break;
                case 'optimize':
                default:
                    $trimHtmlContent = trim($htmlContent);
                    if (isset($htmlContent) && !empty($trimHtmlContent)) {
                        list($content, $code) = $this->getContent($webInfo['optimizeUrl'], $timeout);
                    } else {
                        list($content, $code) = $this->getContent($webInfo['optimizeUrl'], 15);
                    }

                    $trimContent = trim($content);
                    if ($code == 200 && !empty($trimContent)) {
                        if (mb_strpos($content, '你访问的页面不存在或被删除') === false && mb_strpos($content, '请检查网站的站点设置是否填写完整') === false) {
                            $htmlContent = $content;
                        }
                    }
                    break;
            }

            $trimHtmlContent = trim($htmlContent);

            if (!isset($htmlContent) || empty($trimHtmlContent)) {
                header('HTTP/1.1 404 Not Found');
                if (file_exists('404.html')) {
                    include('404.html');
                }
                exit;
            } else {
                $dir = dirname($webInfo['savePath']);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                @file_put_contents($webInfo['savePath'], $htmlContent);
            }
        }

        $this->cacheReplace($htmlContent, $webInfo);


        echo $htmlContent;

    }

    function cacheReplace(&$htmlContent, &$webInfo)
    {
        try {

            libxml_use_internal_errors(true);
            $doc = new \DOMDocument('1.0');
            $doc->resolveExternals = false;
            $doc->validateOnParse = false;
            $doc->substituteEntities = false;

            try {
                if (version_compare(phpversion(), '5.6', '<')) {
                    $doc->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
                } else {
                    $doc->loadHTML($htmlContent);
                }
            } catch (Exception $e) {
            }
            $webInfo['cacheReplaceA'] && $this->cacheReplaceA($doc, $webInfo);
            $doc->formatOutput = true;
            !$webInfo['cacheBug'] && $htmlContent = $doc->saveHTML($doc);
            unset($doc);
        } catch (Exception $e) {
        } catch (Error $e) {
        }
    }


    function cacheReplaceA(&$doc, &$webInfo)
    {
        $nodes = $doc->getElementsByTagName('a');
        $type = 'href';
        $sourceUrlWithoutUri = $this->getSourceUrl($webInfo, false);
        foreach ($nodes as $key => $item) {
            foreach ($item->attributes as $k => $v) {
                if ($k == $type && ($url = $v->value)) {
                    $replace = '';
                    if ($webInfo['domainScheme'] && $webInfo['domain']) {
                        $replace = $webInfo['domainScheme'] . '://' . $webInfo['domain'];
                    }
                    $url = trim(str_replace($sourceUrlWithoutUri . '//', $replace . '/', $url));
                    $url = trim(str_replace($sourceUrlWithoutUri . '?', $replace . '/?', $url));
                    $url = trim(str_replace($sourceUrlWithoutUri, $replace, $url));
                    $url = empty($url) ? '/' : $url;
                    $item->setAttribute($type, $url);
                }
            }
        }
    }

    function getCacheFlag(&$webInfo)
    {
        empty($webInfo['uri']) && $webInfo['uri'] = $_SERVER['REQUEST_URI'];
        $path = parse_url($webInfo['uri']);
        if (isset($path['path'])) {
            $webInfo['path'] = $path['path'];
            if ($webInfo['path'] == '/') {
                $webInfo['path'] = 'index';
            }
        }
        $uuid = '';
        $query = parse_url($webInfo['uri']);
        if (isset($query['query'])) {
            $webInfo['query'] = $query = $query['query'];
            $query = explode('&', $query);
            foreach ((array)$query as $item) {
                $para = (array)explode('=', $item);
                $uuid .= sprintf('_%s-%s', isset($para[0]) ? $para[0] : '', isset($para[1]) ? $para[1] : '');
            }
        }
        return sprintf('/%s/%s%s.%s', $webInfo['cacheDir'], $webInfo['path'], $uuid, $webInfo['cacheSuffix']);
    }

    function getSourceUrl(&$webInfo, $optimize = false, $uri = '')
    {
        if ($uri) {
            $replaceUriSuffix = array('index.php');
            foreach ($replaceUriSuffix as $item) {
                if (mb_strpos($webInfo['uri'], $item) !== 0) {
                    continue;
                }
                $uri = str_replace($item, '', $webInfo['uri']);
            }
        }

        $prefix = 'www';
        if ($webInfo['hasMobile'] && $webInfo['isMobile']) {
            $prefix = 'm';
        }

        if ($optimize) {
            return sprintf('http://%s/%s.%s.%s%s', $webInfo['optimizeDomain'], $prefix, $webInfo['companyId'], $webInfo['sourceDomain'], $uri);
        }

        return sprintf('http://%s.%s.%s%s', $prefix, $webInfo['companyId'], $webInfo['sourceDomain'], $uri);
    }

    function getContent($url, $timeout = 60)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return array($output, $httpCode);
    }

    function init(&$webInfo)
    {

        $webInfo['isMobile'] =false;
        $suffix = '_www';
        $webInfo['isMobile'] && $suffix = '_m';
        $onlyMobileWeb = explode('.', $webInfo['domain']);
        if (isset($onlyMobileWeb[0]) && $onlyMobileWeb[0] == 'm') {
            $webInfo['isMobile'] = true;
            $suffix = '_m';
        }
        $onlyMobileWeb = explode('.', $_SERVER['HTTP_HOST']);
        if (isset($onlyMobileWeb[0]) && $onlyMobileWeb[0] == 'm') {
            $webInfo['isMobile'] = true;
            $suffix = '_m';
        }
        $webInfo['cacheDir'] .= $suffix;

        $webInfo['sourceUrl'] = $this->getSourceUrl($webInfo, false, $webInfo['uri']);
        $webInfo['optimizeUrl'] = $this->getSourceUrl($webInfo, true, $webInfo['uri']);

        $webInfo['cacheFlag'] = $this->getCacheFlag($webInfo);
        $webInfo['savePath'] = __DIR__ . DIRECTORY_SEPARATOR . $webInfo['cacheFlag'];

        $throttle = __DIR__ . DIRECTORY_SEPARATOR . '.throttle';
        $throttleTime = __DIR__ . DIRECTORY_SEPARATOR . '.throttle-time';
        if (!file_exists($throttle) || !file_exists($throttleTime)) {
            @file_put_contents($throttle, $webInfo['throttle']);
            @file_put_contents($throttleTime, time());
        } elseif (time() - file_get_contents($throttleTime) >= 3600) {
            @file_put_contents($throttle, $webInfo['throttle']);
            @file_put_contents($throttleTime, time());
        } else {
            $webInfo['throttle'] = file_get_contents($throttle);
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $userAgent = strtolower($userAgent);
        $spiderFlag = '/(Baiduspider|Googlebot|Bytespider|YisouSpider|Googlebot|bingbot|360Spider|AhrefsBot|Applebot|DotBot|spider)/i';
        if (preg_match($spiderFlag, $userAgent)) {
            $webInfo['isSpider'] = true;
        }

        $webInfo['debug'] && @file_put_contents('debug.json', json_encode($webInfo));
    }


}
