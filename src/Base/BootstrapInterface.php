<?php
namespace Friday\Base;

/**
 * BootstrapInterface is the interface that should be implemented by classes who want to participate in the application bootstrap process.
 *
 * The main method [[bootstrap()]] will be invoked by an application at the beginning of its `init()` method.
 *
 * Bootstrapping classes can be registered in two approaches.
 *
 * The first approach is mainly used by extensions and is managed by the Composer installation process.
 * You mainly need to list the bootstrapping class of your extension in the `composer.json` file like following,
 *
 * ```json
 * {
 *     // ...
 *     "extra": {
 *         "bootstrap": "path\\to\\MyBootstrapClass"
 *     }
 * }
 * ```
 *
 * If the extension is installed, the bootstrap information will be saved in [[Application::extensions]].
 *
 * The second approach is used by application code which needs to register some code to be run during
 * the bootstrap process. This is done by configuring the [[Application::bootstrap]] property:
 *
 * ```php
 * return [
 *     // ...
 *     'bootstrap' => [
 *         "path\\to\\MyBootstrapClass1",
 *         [
 *             'class' => "path\\to\\MyBootstrapClass2",
 *             'prop1' => 'value1',
 *             'prop2' => 'value2',
 *         ],
 *     ],
 * ];
 * ```
 *
 * As you can see, you can register a bootstrapping class in terms of either a class name or a configuration class.
 */
interface BootstrapInterface
{
    /**
     * Bootstrap method to be called during application bootstrap stage.
     * @param AbstractApplication $app the application currently running
     */
    public function bootstrap($app);
}
