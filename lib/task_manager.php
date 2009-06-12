<?php
/*	Project:        Brutis
	Version:        0.91
	Author:         Zach Younker
	Copyright:

		Software License Agreement (BSD License)

		Copyright (c) 2009, Gear Six, Inc.
		All rights reserved.

		Redistribution and use in source and binary forms, with or without
		modification, are permitted provided that the following conditions are
		met:

		* Redistributions of source code must retain the above copyright
		  notice, this list of conditions and the following disclaimer.

		* Redistributions in binary form must reproduce the above
		  copyright notice, this list of conditions and the following disclaimer
		  in the documentation and/or other materials provided with the
		  distribution.

		* Neither the name of Gear Six, Inc. nor the names of its
		  contributors may be used to endorse or promote products derived from
		  this software without specific prior written permission.

		THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
		"AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
		LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
		A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
		OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
		SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
		LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
		DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
		THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
		(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
		OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

*/

/* Sleep 0.3 seconds between child execution */
define('SLEEP_BETWEEN_EXEC', 300000000); 

/* Turn on all error reporting */
error_reporting(E_ALL);

class Task {
	protected $pid;
    	protected $ppid;
	protected $args;
	protected $timer;

	function __construct() {
		/* initialize some variables on creation*/
		$this->args = array();
 		$this->timer = array();
 		$this->ppid = 0;
		$this->pid = 0;
	}

	function fork() {
		/* Fork processes */
		$pid = pcntl_fork();
 		if ($pid == -1) {
			printf("Error, Could not fork process!\n");
			exit(1);
		} elseif ($pid) {
			/* In Parent, save pid */
			$this->pid = $pid;
		} else {
			/* Execute child process */
			$this->exec();
		}
	}

	function start_timer() {
		/* Save start time */
		$this->timer['start_time'] = microtime(TRUE);
	}

	function end_timer() {
		/* Save end time */
		$this->timer['end_time'] = microtime(TRUE);
		$this->timer['runtime'] = $this->timer['end_time'] - $this->timer['start_time'];
	}

	function maxruntime() {
		/* Return maximum runtime */
		return $this->args['maxruntime'];
	}

	function current_runtime() {
		/* return current runtime */
		if (!isset($this->timer['start_time'])) {
			$this->timer['start_time'] = microtime(TRUE);
		}
		return microtime(TRUE) - $this->timer['start_time'];
	}

	function pid() {
		/* return pid of current process */
		return $this->pid;
	}

	function set_args($args) {
		/* set runtime arguments for child */
		$this->args = $args;
	}

	function exec() {
		/* setup dummy function to be extended */
		$this->ppid = posix_getppid();
		$this->pid = posix_getpid();
	}

	function finish() {
		/* setup dummy function to be extended */
	}
}

class TaskManager {
	protected $pool;
	protected $alarm;

	function __construct() {
		/* initialize variables on creation */
		$this->pool = array();
	}

	function add_task($task,$args) {
		/* add a new task to the pool */
		$task->set_args($args);
		$this->pool[] = $task;
		$this->alarm = FALSE;
	}

	function check_runtime($signo) {
		/* Check runtime of all tasks and kill if they run over maxruntime */
		foreach ($this->pool as $task) {
			if ($task->maxruntime() <= $task->current_runtime()) {
				printf("Hit timeout of " . $task->maxruntime() . " for PID: " . $task->pid());
				printf("Sending SIGTERM(15) to PID: " . $task->pid());
				posix_kill($task->pid(), 15);
			}
		}
		pcntl_alarm(1);
		/* Set alarm to true, so we know we exited because or runtime check */
		$this->alarm = TRUE;
	}

	function run() {
		/* Main loop to run child code */

		$client_exit_code = 0;

		/* setup SIGALRM to check for runaway tasks every 1 second */
		declare(ticks = 1);
		pcntl_signal(SIGALRM, array(&$this, 'check_runtime'), FALSE);
		pcntl_alarm(1);

		/* go through pool and fork tasks */
		foreach($this->pool as $task){
			$task->start_timer();
			$task->fork();
			time_nanosleep(0, SLEEP_BETWEEN_EXEC);
		}

		while(1){
			/* wait for tasks to finish. */
           		$pid = pcntl_wait($extra);
			if (($pid == -1) && ($this->alarm == FALSE)) {
				/* all childs exited, stop loop */
                		break;
			}
			if (($pid != -1) && ($this->alarm == FALSE)) {
				if ($extra >= 1) {
					$client_exit_code = pcntl_wexitstatus($extra);
				}
            			self::finish_task($pid, $client_exit_code);
			}
			if ($this->alarm == TRUE) {
				$this->alarm = FALSE;
			}
		}

		/* All childs exited, time to quit */
		exit($client_exit_code);
	}

	function finish_task($pid, $exit_code){
		/* run finish function on child exit */
		if($task = $this->pid_to_task($pid)) {
			$task->end_timer();
			$task->exit_code = $exit_code;
			$task->finish();
		}
	}

	function pid_to_task($pid){
		/* convert pid to task and return task */
		foreach($this->pool as $task){
			if($task->pid() == $pid)
				return $task;
			}
 			return false;
 		}
	}
?>
