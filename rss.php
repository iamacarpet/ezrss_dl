#!/usr/bin/php
<?php

require_once('lib/lastRSS.inc');

date_default_timezone_set('Europe/London');

$rss = new lastRSS;
$rss->cache_dir = '';
$rss->cache_time = 0;
$rss->cp = 'US-ASCII';
$rss->date_format = 'l';
$rss->CDATA = 'content';

$uri = 'http://ezrss-server/cgi-bin/eztv.pl';

$feed = @$rss->get($uri);

$shows = array(
		'Show Name'
);

$db = new MySQLi('localhost', 'dbuser', 'dbpass', 'dbname');
$sql = 'CREATE TABLE IF NOT EXISTS episodes ( id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255), season INT, episode INT );';
$db->query($sql);

foreach ($shows as $show){
    $matches = array();
    
    foreach( $feed['items'] as $item ){
        if (preg_match( '/^' . $show . ' S([0-9]{2})E([0-9]{2})/', $item['title'], $m )){
            $item['season'] = $m[1];
            $item['episode'] = $m[2];
            
            if (preg_match('/1080p/', $item['title'])){
                $item['quality'] = 1080;
            } elseif (preg_match('/720p/', $item['title'])){
                $item['quality'] = 720;
            } else {
                $item['quality'] = 10;
            }
            
            $matches[$item['season']][$item['episode']][$item['quality']][] = $item;
        }
    }
    
    foreach($matches as $s => $season){
        foreach($season as $e => $episode){
            if (! have_episode($show, $s, $e) /* check here if we've already got this episode */){
                // Try for 1080p version first.
                if (@is_array($episode[1080])){
                    download_torrent($show, $s, $e, array_shift($episode[1080]));
                } else if (@is_array($episode[720])){
                    download_torrent($show, $s, $e, array_shift($episode[720]));
                } else if (@is_array($episode[10])){
                    download_torrent($show, $s, $e, array_shift($episode[10]));
                }
            }
        }
    }
    
}
            
function have_episode($name, $s, $e){
    global $db;
    
    $sql = "SELECT id, name FROM episodes WHERE name = '" . $db->escape_string($name) . "' AND season = " . $db->escape_string($s) . " AND episode = " . $db->escape_string($e);

    $res = $db->query($sql);

    if ($res->num_rows > 0) { 
        return true;
    } else {
        return false;
    }
}

function download_torrent($name, $s, $e, $magnet_link){
    global $db;
    
    $sql = "INSERT INTO episodes (name, season, episode) VALUES ( '" . $db->escape_string($name) . "', " . $db->escape_string($s) . ", " . $db->escape_string($e) . " )";
    
    $db->query($sql);
    
    echo "Downloading Episode...\n";
    
    $shell = './magnet2torrent ' . escapeshellarg($magnet_link['link']) . ' ' . escapeshellarg('torrents/' . generateRandomString(25) . '.torrent');
   
    shell_exec($shell);
}
        
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
