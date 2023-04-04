<?php

namespace App\Http\Controllers;

use Illuminate\View\View;


class IndexController extends Controller
{

    protected array $webInfo = [

        'cacheBug' => '0',
        'cacheTTL' => '300',

        'cacheReplaceA' => '1',
        'mtimeTTL' => '2592000',
        'throttle' => '30',
        'sourceDomain' => 'cadz.org.cn',
        'cacheDir' => 'cache',
        'cacheSuffix' => 'html',
        'uri' => '',
        'optimizeUrl' => '',
        'cacheFlag' => '',
        'savePath' => '',
        'path' => '',
        'query' => '',
        'isSpider' => false,
    ];

    public function index(): View
    {
        try {
            $this->webInfo['uri'] = $_SERVER['REQUEST_URI'];
            $this->main($this->webInfo);
        } catch (Exception $e) {
            header('HTTP/1.1 404 Not Found');
        } catch (Error $e) {
            header('HTTP/1.1 404 Not Found');
        }
        return view('404');
    }


    function main(&$webInfo)
    {
        $this->init($webInfo);
        $htmlContent = '';
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


            $trimHtmlContent = trim($htmlContent);

            if (!isset($htmlContent) || empty($trimHtmlContent)) {
                header('HTTP/1.1 404 Not Found');

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
                $doc->loadHTML($htmlContent);
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

        $webInfo['cacheDir'] .= '_www';

        $webInfo['optimizeUrl'] = sprintf('http://%s.%s%s', 'www', $webInfo['sourceDomain'], $$webInfo['uri']);

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

    }


}
