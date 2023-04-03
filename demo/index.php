<?php

error_reporting(E_ERROR);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'Mobile_Detect.php';

$fileMyqcloud = null;
$fileMyqcloudContent = null;

$webInfo = array(
    'companyId' => '100070',
    'domain' => '',
    'domainScheme' => '',
    'domainJump' => '',
    'cacheBug' => '0',
    'cacheTTL' => '300',
    'cacheReplaceImg' => '1',
    'cacheReplaceVideo' => '1',
    'cacheReplaceScript' => '1',
    'cacheReplaceLink' => '1',
    'cacheReplaceA' => '1',
    'cacheReplaceBGImg' => '1',
    'mtimeTTL' => '2592000',
    'throttle' => '30',
    'hasMobile' => '1',

    'optimizeDomain' => '180.76.55.80',
    'sourceDomain' => 'xiaohucloud.com',
    'imgDomain' => 'img-xhyftp.xiaohucloud.cn',
    'env' => ' ',
    'cacheDir' => 'cache',
    'cacheSuffix' => 'html',
    'uri' => $_SERVER['REQUEST_URI'],
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
    'isSpider' => false
);

function main(&$webInfo)
{
    init($webInfo);

    if (isset($webInfo['domainJump']) &&
        isset($webInfo['domain']) &&
        isset($_SERVER['HTTP_HOST']) &&
        isset($webInfo['domainScheme']) &&
        !empty($webInfo['domainJump']) &&
        $_SERVER['HTTP_HOST'] == $webInfo['domainJump']
    ) {
        $url = sprintf('Location:%s://%s%s', $webInfo['domainScheme'], $webInfo['domain'], $webInfo['uri']);
        header($url, true, 301);
        exit;
    }

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
                    list($content, $code) = getContent($webInfo['sourceUrl'], $timeout);
                } else {
                    list($content, $code) = getContent($webInfo['sourceUrl'], 15);
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
                    list($content, $code) = getContent($webInfo['optimizeUrl'], $timeout);
                } else {
                    list($content, $code) = getContent($webInfo['optimizeUrl'], 15);
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

    cacheReplace($htmlContent, $webInfo);
    $webInfo['cacheReplaceBGImg'] && cacheReplaceBgImg($htmlContent, $webInfo);

    echo $htmlContent;

    global $fileMyqcloud;
    @fclose($fileMyqcloud);
}

function cacheReplace(&$htmlContent, &$webInfo)
{
    try {
        $doc = new DOMDocument('1.0');
        $doc->resolveExternals = false;
        $doc->validateOnParse = false;
        $doc->substituteEntities = false;
        try {
            if (version_compare(phpversion(),'5.6', '<')) {
                $doc->loadHTML(mb_convert_encoding($htmlContent, 'HTML-ENTITIES', 'UTF-8'));
            } else {
                $doc->loadHTML($htmlContent);
            }
        } catch (Exception $e) {
        }
        $webInfo['cacheReplaceImg'] && cacheReplaceTag($doc, $webInfo, 'img', 'src');
        $webInfo['cacheReplaceVideo'] && cacheReplaceTag($doc, $webInfo, 'source', 'src');
        $webInfo['cacheReplaceScript'] && cacheReplaceTag($doc, $webInfo, 'script', 'src');
        $webInfo['cacheReplaceLink'] && cacheReplaceTag($doc, $webInfo, 'link', 'href');
        $webInfo['cacheReplaceA'] && cacheReplaceA($doc, $webInfo);
        $doc->formatOutput = true;
        !$webInfo['cacheBug'] && $htmlContent = $doc->saveHTML($doc);
        unset($doc);
    } catch (Exception $e) {
    } catch (Error $e) {
    }
}

function cacheReplaceBgImg(&$htmlContent, &$webInfo)
{
    preg_match_all('/((http[s]?:)?\/\/(img|diy).' . 'xiaohucloud.(cn|com)' . '(.*?\.png|.*?\.jpg|.*?\.mp4|.*?\.css|.*?\.js))/', $htmlContent, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $key => $url) {
            if (!filterUrl($url, $webInfo)) {
                continue;
            }
            $path = parse_url($url);
            $path = $path['path'];
            $savePath = __DIR__ . DIRECTORY_SEPARATOR . $path;
            if (file_exists($savePath)) {
                $htmlContent = str_replace($url, $path, $htmlContent);
                continue;
            } else {
                writeMyqcloud($url, $webInfo);
            }
        }
    }
    preg_match_all('/((http[s]?:)?\\\\\/\\\\\/(img|diy).' . 'xiaohucloud.(cn|com)' . '(.*?\.png|.*?\.jpg|.*?\.mp4|.*?\.css|.*?\.js))/', $htmlContent, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $key => $url) {
            $url = stripcslashes($url);
            if (!filterUrl($url, $webInfo)) {
                continue;
            }
            $path = parse_url($url);
            $path = $path['path'];
            $savePath = __DIR__ . DIRECTORY_SEPARATOR . $path;
            if (file_exists($savePath)) {
                $htmlContent = str_replace(addcslashes($url, '/'), addcslashes($path, '/'), $htmlContent);
                continue;
            } else {
                writeMyqcloud($url, $webInfo);
            }
        }
    }

    if ($webInfo['sftp']) {
        $htmlContent = str_replace('https://', 'http://', $htmlContent);
    }

    list($content, $code) = getContent($webInfo['imgDomain'] . '/lock.html', 0.1);
    if ($code == 200) {
        $htmlContent = str_replace('img.' . $webInfo['sourceDomain'], $webInfo['imgDomain'], $htmlContent);
    }

    list($content, $code) = getContent($webInfo['diyDomain'] . '/lock.html', 0.1);
    if ($code == 200) {
        $htmlContent = str_replace('diy.' . $webInfo['sourceDomain'], $webInfo['diyDomain'], $htmlContent);
    }

    list($content, $code) = getContent($webInfo['cosDomain'] . '/lock.html', 0.1);
    if ($code == 200) {
        $htmlContent = str_replace('xhy-1254204867.cos.ap-chengdu.myqcloud.com', $webInfo['cosDomain'], $htmlContent);
    }

    preg_match_all('/(http[s]?:\\\\\/\\\\\/(m|www).' . $webInfo['companyId'] . '.' . $webInfo['sourceDomain'] . ')/', $htmlContent, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $key => $url) {
            if (mb_strpos($url, $webInfo['sourceDomain']) === false) {
                continue;
            }
            $path = '';
            if (!empty($webInfo['domainScheme']) && !empty($webInfo['domain'])) {
                $path = sprintf('%s://%s', $webInfo['domainScheme'], $webInfo['domain']);
            }
            $htmlContent = str_replace($url, addcslashes($path, '/'), $htmlContent);
        }
    }

    $htmlContent = str_replace('http://m.'.$webInfo['companyId'].'.'.$webInfo['sourceDomain'] . '"', '/"', $htmlContent);
    $htmlContent = str_replace('http://www.'.$webInfo['companyId'].'.'.$webInfo['sourceDomain'] . '"', '/"', $htmlContent);

    preg_match_all('/(http[s]?:\/\/(m|www).' . $webInfo['companyId'] . '.' . $webInfo['sourceDomain'] . '\??)/', $htmlContent, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $key => $url) {
            if (mb_strpos($url, $webInfo['sourceDomain']) === false) {
                continue;
            }
            $path = '';
            if (!empty($webInfo['domainScheme']) && !empty($webInfo['domain'])) {
                $path = sprintf('%s://%s', $webInfo['domainScheme'], $webInfo['domain']);
            }
            if (mb_strpos($url, '?') !== false) {
                $htmlContent = str_replace($url, $path . '/?', $htmlContent);
            } else {
                $htmlContent = str_replace($url, $path, $htmlContent);
            }
        }
    }

    preg_match_all('/((http[s]?):\/\/mp.xiaohucloud' . ')/', $htmlContent, $matches);
    if (isset($matches[1])) {
        foreach ($matches[1] as $key => $url) {
            $path = sprintf('%s://%s', $webInfo['domainScheme'] ? $webInfo['domainScheme'] : 'http', 'mp.xiaohucloud');
            $htmlContent = str_replace($url, $path, $htmlContent);
        }
    }
}

function cacheReplaceA(&$doc, &$webInfo)
{
    $nodes = $doc->getElementsByTagName('a');
    $type = 'href';
    $sourceUrlWithoutUri = getSourceUrl($webInfo, false);
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

function writeMyqcloud($url, &$webInfo)
{
    if (!filterUrl($url, $webInfo)) {
        return false;
    }
    $fileSize = 0;
    global $fileMyqcloud;
    global $fileMyqcloudContent;
    if (file_exists(__DIR__ . DIRECTORY_SEPARATOR . '.myqcloud')) {
        $fileSize = filesize(__DIR__ . DIRECTORY_SEPARATOR . '.myqcloud');
    }
    if (is_null($fileMyqcloud) && is_null($fileMyqcloudContent) && $fileSize < 10000000) {
        $fileMyqcloudContent = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . '.myqcloud');
        $fileMyqcloud = @fopen(__DIR__ . DIRECTORY_SEPARATOR . '.myqcloud', 'a+');
    }
    if (!is_null($fileMyqcloud) && !is_null($fileMyqcloudContent)) {
        if (mb_strpos($fileMyqcloudContent, $url) === false) {
            @fwrite($fileMyqcloud, $url . "\n");
        }
    }
}

function cacheReplaceTag(&$doc, &$webInfo, $tagName = 'img', $type = 'src')
{
    $nodes = $doc->getElementsByTagName($tagName);

    foreach ($nodes as $key => $item) {
        foreach ($item->attributes as $k => $v) {
            if ($k == $type && ($url = $v->value)) {
                if ($tagName == 'img') {
                    $item->setAttribute('loading', 'lazy');
                }

                if (!filterUrl($url, $webInfo)) {
                    continue;
                }

                $path = parse_url($url);
                $path = $path['path'];
                $savePath = __DIR__ . DIRECTORY_SEPARATOR . $path;
                if (file_exists($savePath)) {
                    $item->setAttribute($type, $path);
                    continue;
                } else {
                    writeMyqcloud($url, $webInfo);
                }
            }
        }
    }
}

function filterUrl($url, &$webInfo)
{
    if (mb_strpos($url, '//') === 0) {
        $url = 'https:' . $url;
    }
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }
    if (mb_strpos($url, '"') !== false) {
        return false;
    }
    if (mb_strpos($url, ';') !== false) {
        return false;
    }
    if (mb_strpos($url, ')') !== false) {
        return false;
    }
    if (mb_strpos($url, 'xiaohucloud.com') === false) {
        return false;
    }
    echo $url;
    echo '<BR/>';
    $path = parse_url($url);
    if (!isset($path['path'])) {
        return false;
    }
    return true;
}

function addResponseHeader()
{
    header("X-XSS-Protection: 1");
    header('X-Content-Type-Options: nosniff');
    header("Referrer-Policy: origin");
    header("X-Permitted-Cross-Domain-Police:value");
    header("X-Content-Security-Policy: default-src 'self' ");
    header('X-Frame-Options: deny');
    header("content-type:text/html;charset=utf-8");
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
    $agent = new Mobile_Detect();
    $webInfo['isMobile'] = $agent->isMobile();
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

    $webInfo['sourceUrl'] = getSourceUrl($webInfo, false, $webInfo['uri']);
    $webInfo['optimizeUrl'] = getSourceUrl($webInfo, true, $webInfo['uri']);

    $webInfo['cacheFlag'] = getCacheFlag($webInfo);
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

try {
    main($webInfo);
} catch (Exception $e) {
    header('HTTP/1.1 404 Not Found');
} catch (Error $e) {
    header('HTTP/1.1 404 Not Found');
}

exit;
