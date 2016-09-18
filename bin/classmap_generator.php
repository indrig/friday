<?php

$path = realpath(__DIR__ . '/../src/');
$di = new RecursiveDirectoryIterator($path);
$ita = new RecursiveIteratorIterator($di);
$regex = new RegexIterator($ita, '/^.+\.php$/i',
    RecursiveRegexIterator::GET_MATCH);

$content = '<?php' . "\n";
$content .= "return [\n";
$len = strlen($path);
foreach( $regex as $matches ) foreach( $matches as $match ) {
    try {

       $name =  str_replace('\\', '/', substr($match, $len + 1));

        $dirName = dirname($name);
        if($dirName === '.' || $dirName == 'bin'){
            continue;
        }

        $class = 'Friday\\' . str_replace('/', '\\', substr($name, 0, -4));

        $content .= "'{$class}' => __DIR__ . '/{$name}',\n";
    }
    catch ( Exception $e ) {
        echo "$match class load failed.\n";
    }
}
$content .= '];';
file_put_contents(__DIR__ . '/../src/autoload_classmap.php',$content);
