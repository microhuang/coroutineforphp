<?php

class Task {
    protected $taskId;
    protected $coroutine;
    protected $sendValue = null;
    protected $beforeFirstYield = true;

    public function __construct($taskId, Generator $coroutine) {
        $this->taskId = $taskId;
        $this->coroutine = $coroutine;
    }

    public function getTaskId() {
        return $this->taskId;
    }

function getTaskId_v2() {
    return new SystemCall(function(Task $task, Scheduler $scheduler) {
        $task->setSendValue($task->getTaskId());
        $scheduler->schedule($task);
    });
}

    public function setSendValue($sendValue) {
        $this->sendValue = $sendValue;
    }

    public function run() {
        if ($this->beforeFirstYield) {
            $this->beforeFirstYield = false;
            return $this->coroutine->current();
        } else {
            $retval = $this->coroutine->send($this->sendValue);
            $this->sendValue = null;
            return $retval;
        }
    }

public function run_v2() {
    while (!$this->taskQueue->isEmpty()) {
        $task = $this->taskQueue->dequeue();
        $retval = $task->run();

        if ($retval instanceof SystemCall) {
            $retval($task, $this);
            continue;
        }

        if ($task->isFinished()) {
            unset($this->taskMap[$task->getTaskId()]);
        } else {
            $this->schedule($task);
        }
    }
}

    public function isFinished() {
        return !$this->coroutine->valid();
    }
}


class Scheduler {
    protected $maxTaskId = 0;
    protected $taskMap = []; // taskId => task
    protected $taskQueue;

    public function __construct() {
        $this->taskQueue = new SplQueue();
    }

    public function newTask(Generator $coroutine) {
        $tid = ++$this->maxTaskId;
        $task = new Task($tid, $coroutine);
        $this->taskMap[$tid] = $task;
        $this->schedule($task);
        return $tid;
    }

    public function schedule(Task $task) {
        $this->taskQueue->enqueue($task);
    }

    public function run() {
        while (!$this->taskQueue->isEmpty()) {
            $task = $this->taskQueue->dequeue();
            $task->run();

            if ($task->isFinished()) {
                unset($this->taskMap[$task->getTaskId()]);
            } else {
                $this->schedule($task);
            }
        }
    }
}


class SystemCall {
    protected $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function __invoke(Task $task, Scheduler $scheduler) {
        $callback = $this->callback; // Can't call it directly in PHP :/
        return $callback($task, $scheduler);
    }
}

function task1() {
    for ($i = 1; $i <= 10; ++$i) {
        echo "This is task 1 iteration $i.\n";
        yield;                //must be co
    }
}

function task2() {
    for ($i = 1; $i <= 5; ++$i) {
        echo "This is task 2 iteration $i.\n";
        yield;                //must be co
    }
}

function task($max){
	$tid=(yield getTaskId()); // <-- here's the syscall!
	for($i=1;$i<=$max;++$i){
		echo "This is task $tid iteration $i.\n";
		yield;        //must be co
	}
}

$scheduler = new Scheduler;

$scheduler->newTask(task1());
$scheduler->newTask(task2());

//$scheduler->newTask(task(10));
//$scheduler->newTask(task(5));

$scheduler->run();   //输出结果：task1、task2交替运行，当task2结束后，task1继续运行直到完成退出。
