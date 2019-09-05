<?php
namespace EasyTask;

use \Closure as Closure;
use EasyTask\Process\Linux;
use EasyTask\Process\Win;
use \ReflectionClass as ReflectionClass;
use \ReflectionMethod as ReflectionMethod;
use \ReflectionException as ReflectionException;

class Task
{
    /**
     * 是否守护进程
     * @var bool
     */
    private $daemon = false;

    /**
     * 是否清空文件掩码
     * @var bool
     */
    private $umask = false;

    /**
     * 是否卸载工作区
     * @var bool
     */
    private $isChdir = false;

    /**
     * 关闭标准输入输出
     * @var bool
     */
    private $closeInOut = false;

    /**
     * 是否支持异步信号
     * @var bool
     */
    private $canAsync = false;

    /**
     * 任务前缀(作为进程名称前缀)
     * @var string
     */
    private $prefix = 'EasyTask';

    /**
     * 当前Os平台
     * @var int
     */
    private $currentOs = 1;

    /**
     * 任务列表
     * @var array
     */
    private $taskList = [];

    /**
     * 构造函数
     */
    public function __construct()
    {
        //检查异步支持
        $this->canAsync = $this->canAsync();

        //获取运行平台
        $this->currentOs = $this->currentOs();
    }

    /**
     * 获取当前运行平台
     * @return int
     */
    private function currentOs()
    {
        return Helper::isWin() ? 1 : 2;
    }

    /**
     * 检查是否支持异步
     * @return bool
     */
    private function canAsync()
    {
        return Helper::canAsyncSignal();
    }

    /**
     * 拦截器
     * @param string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * 设置守护
     * @param bool $daemon 是否守护
     * @return $this
     */
    public function setDaemon($daemon = false)
    {
        $this->daemon = $daemon;
        return $this;
    }

    /**
     * 是否清空文件掩码
     * @param bool $umask 默认不清空
     * @return $this
     */
    public function setUmask($umask = false)
    {
        $this->umask = $umask;
        return $this;
    }

    /**
     * 卸载工作区
     * @param bool $isChdir 是否卸载所在工作区
     * @return $this
     */
    public function setChdir($isChdir = false)
    {
        $this->isChdir = $isChdir;
        return $this;
    }

    /**
     * 设置关闭标准输入输出
     * @param bool $closeInOut
     * @return $this
     */
    public function setInOut($closeInOut = false)
    {
        $this->closeInOut = $closeInOut;
        return $this;
    }

    /**
     * 设置任务前缀
     * @param string $prefix
     * @return $this
     */
    public function setPrefix($prefix = '')
    {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * 新增匿名函数作为任务
     * @param Closure $func 匿名函数
     * @param string $alas 任务别名
     * @param int $time 定时器间隔
     * @param int $used 定时器占用进程数
     * @return $this
     * @throws
     */
    public function addFunc($func, $alas = '', $time = 1, $used = 1)
    {
        if (!($func instanceof Closure))
        {
            Helper::exception('func must instanceof Closure');
        }

        $alas = $alas ? $alas : uniqid();
        $uniKey = md5($alas);
        $this->taskList[$uniKey] = [
            'type' => 1,
            'func' => $func,
            'alas' => $alas,
            'time' => $time,
            'used' => $used,
        ];

        return $this;
    }

    /**
     * 新增类作为任务
     * @param string $class 类名称
     * @param string $func 方法名称
     * @param string $alas 任务别名
     * @param int $time 定时器间隔
     * @param int $used 定时器占用进程数
     * @return $this
     * @throws
     */
    public function addClass($class, $func, $alas = '', $time = 1, $used = 1)
    {
        if (!class_exists($class))
        {
            Helper::exception("class {$class} is not exist");
        }
        try
        {
            $reflect = new ReflectionClass($class);
            if (!$reflect->hasMethod($func))
            {
                Helper::exception("class {$class}'s func {$func} is not exist");
            }
            $method = new ReflectionMethod($class, $func);
            if (!$method->isPublic())
            {
                Helper::exception("class {$class}'s func {$func} must public");
            }
            $alas = $alas ? $alas : uniqid();
            $uniKey = md5($alas);
            $this->taskList[$uniKey] = [
                'type' => $method->isStatic() ? 2 : 3,
                'func' => $func,
                'alas' => $alas,
                'time' => $time,
                'used' => $used,
                'class' => $class,
            ];
        }
        catch (ReflectionException $exception)
        {
            Helper::exception($exception->getMessage());
        }

        return $this;
    }

    /**
     * 获取进程管理实例
     * @return  Win | Linux
     */
    private function getProcess()
    {
        if ($this->currentOs == 1)
        {
            return (new Win($this));
        }
        else
        {
            return (new Linux($this));
        }
    }

    /**
     * 开始运行
     * @throws
     */
    public function start()
    {
        if (!$this->taskList)
        {
            return;
        }
        ($this->getProcess())->start();
    }

    /**
     * 运行状态
     */
    public function status()
    {
        ($this->getProcess())->status();
    }

    /**
     * 停止运行
     * @param bool $force 是否强制
     */
    public function stop($force)
    {
        ($this->getProcess())->stop($force);
    }
}