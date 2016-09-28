<?php
namespace Friday\Web;

use Friday\Base\Component;

/**
 * Session provides session data management and the related configurations.
 *
 * Session is a Web application component that can be accessed via `Yii::$app->session`.
 *
 * To start the session, call [[open()]]; To complete and send out session data, call [[close()]];
 * To destroy the session, call [[destroy()]].
 *
 * Session can be used like an array to set and get session data. For example,
 *
 * ```php
 * $session = new Session;
 * $session->open();
 * $value1 = $session['name1'];  // get session variable 'name1'
 * $value2 = $session['name2'];  // get session variable 'name2'
 * foreach ($session as $name => $value) // traverse all session variables
 * $session['name3'] = $value3;  // set session variable 'name3'
 * ```
 *
 * Session can be extended to support customized session storage.
 * To do so, override [[useCustomStorage]] so that it returns true, and
 * override these methods with the actual logic about using custom storage:
 * [[openSession()]], [[closeSession()]], [[readSession()]], [[writeSession()]],
 * [[destroySession()]] and [[gcSession()]].
 *
 * Session also supports a special type of session data, called *flash messages*.
 * A flash message is available only in the current request and the next request.
 * After that, it will be deleted automatically. Flash messages are particularly
 * useful for displaying confirmation messages. To use flash messages, simply
 * call methods such as [[setFlash()]], [[getFlash()]].
 *
 * @property array $allFlashes Flash messages (key => message or key => [message1, message2]). This property
 * is read-only.
 * @property array $cookieParams The session cookie parameters. This property is read-only.
 * @property integer $count The number of session variables. This property is read-only.
 * @property string $flash The key identifying the flash message. Note that flash messages and normal session
 * variables share the same name space. If you have a normal session variable using the same name, its value will
 * be overwritten by this method. This property is write-only.
 * @property float $gCProbability The probability (percentage) that the GC (garbage collection) process is
 * started on every session initialization, defaults to 1 meaning 1% chance.
 * @property boolean $hasSessionId Whether the current request has sent the session ID.
 * @property string $id The current session ID.
 * @property boolean $isActive Whether the session has started. This property is read-only.
 * @property SessionIterator $iterator An iterator for traversing the session variables. This property is
 * read-only.
 * @property string $name The current session name.
 * @property string $savePath The current session save path, defaults to '/tmp'.
 * @property integer $timeout The number of seconds after which data will be seen as 'garbage' and cleaned up.
 * The default value is 1440 seconds (or the value of "session.gc_maxlifetime" set in php.ini).
 * @property boolean|null $useCookies The value indicating whether cookies should be used to store session
 * IDs.
 * @property boolean $useCustomStorage Whether to use custom storage. This property is read-only.
 * @property boolean $useTransparentSessionID Whether transparent sid support is enabled or not, defaults to
 * false.

 */
class Session extends Component
{
    use ConnectionContextTrait;
}
