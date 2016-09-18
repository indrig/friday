<?php
namespace Friday\Web;

use Friday;
use Friday\Base\Component;
use Friday\Base\Exception\Exception;
use Friday\Helper\AliasHelper;

/**
 * AssetConverter supports conversion of several popular script formats into JS or CSS scripts.
 *
 * It is used by [[AssetManager]] to convert files after they have been published.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class AssetConverter extends Component implements AssetConverterInterface
{
    /**
     * @var array the commands that are used to perform the asset conversion.
     * The keys are the asset file extension names, and the values are the corresponding
     * target script types (either "css" or "js") and the commands used for the conversion.
     *
     * You may also use a path alias to specify the location of the command:
     *
     * ```php
     * [
     *     'styl' => ['css', '@app/node_modules/bin/stylus < {from} > {to}'],
     * ]
     * ```
     */
    public $commands = [
        'less' => ['css', 'lessc {from} {to} --no-color --source-map'],
        'scss' => ['css', 'sass {from} {to} --sourcemap'],
        'sass' => ['css', 'sass {from} {to} --sourcemap'],
        'styl' => ['css', 'stylus < {from} > {to}'],
        'coffee' => ['js', 'coffee -p {from} > {to}'],
        'ts' => ['js', 'tsc --out {to} {from}'],
    ];
    /**
     * @var boolean whether the source asset file should be converted even if its result already exists.
     * You may want to set this to be `true` during the development stage to make sure the converted
     * assets are always up-to-date. Do not set this to true on production servers as it will
     * significantly degrade the performance.
     */
    public $forceConvert = false;


    /**
     * Converts a given asset file into a CSS or JS file.
     * @param string $asset the asset file path, relative to $basePath
     * @param string $basePath the directory the $asset is relative to.
     * @return string the converted asset file path, relative to $basePath.
     */
    public function convert($asset, $basePath)
    {
        $pos = strrpos($asset, '.');
        if ($pos !== false) {
            $ext = substr($asset, $pos + 1);
            if (isset($this->commands[$ext])) {
                list ($ext, $command) = $this->commands[$ext];
                $result = substr($asset, 0, $pos + 1) . $ext;
                if ($this->forceConvert || @filemtime("$basePath/$result") < @filemtime("$basePath/$asset")) {
                    $this->runCommand($command, $basePath, $asset, $result);
                }

                return $result;
            }
        }

        return $asset;
    }

    /**
     * Runs a command to convert asset files.
     * @param string $command the command to run. If prefixed with an `@` it will be treated as a path alias.
     * @param string $basePath asset base path and command working directory
     * @param string $asset the name of the asset file
     * @param string $result the name of the file to be generated by the converter command
     * @return boolean true on success, false on failure. Failures will be logged.
     * @throws \Friday\Base\Exception\Exception when the command fails and YII_DEBUG is true.
     * In production mode the error will be logged.
     */
    protected function runCommand($command, $basePath, $asset, $result)
    {
        $command = AliasHelper::getAlias($command);
        
        $command = strtr($command, [
            '{from}' => escapeshellarg("$basePath/$asset"),
            '{to}' => escapeshellarg("$basePath/$result"),
        ]);
        $descriptor = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $proc = proc_open($command, $descriptor, $pipes, $basePath);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        foreach ($pipes as $pipe) {
            fclose($pipe);
        }
        $status = proc_close($proc);

        if ($status === 0) {
            Friday::trace("Converted $asset into $result:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr", __METHOD__);
        } elseif (FRIDAY_DEBUG) {
            throw new Exception("AssetConverter command '$command' failed with exit code $status:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr");
        } else {
            Friday::error("AssetConverter command '$command' failed with exit code $status:\nSTDOUT:\n$stdout\nSTDERR:\n$stderr", __METHOD__);
        }

        return $status === 0;
    }
}
