<?php
/*	Project:        Brutis
	Version:        0.89
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

function check_libs() {
	/* Check to make sure required libraries are available */
	global $settings;

	if (class_exists("Memcache") == 0) {
		printf("Error, Missing required library 'memcache', exiting\n");
		exit(1);
	} else {
		if (class_exists("memcachePool") == 0) {
			$settings['memcachelib_version'] = 2;
		} else {
			$settings['memcachelib_version'] = 3;
		}
	}

        @include("Net/Server.php");
        if (class_exists("Net_Server") == 0) {
                printf("Error, Missing required library 'Net_Server', exiting.\n");
                exit(1);
        }
        @include("Net/Socket.php");
        if (class_exists("Net_Socket") == 0) {
                printf("Error, Missing required library 'Net_Socket', exiting.\n");
                exit(1);
        }
}


function check_arg($arglist, $arg) {
/*	check_arg()
	Check to see if argument has been given multiple times
	@params string $host hostname to validate	 
*/

	if (isset($arglist[$arg])) {	
		if (is_array($arglist[$arg])) {
			printf("Error, multiple -$arg arguments specified!\n");
			exit(1);
		}
	}
}

function valid_host($host) {
/*	valid_host()
	Validate that hostname uses correct chars, and has DNS name associated 
	@params string $host hostname to validate	 
	@return bool
*/
	if (preg_match('/^[a-zA-Z0-9_-]{1,255}$/', $host)) {
		if ($host == 'localhost') {
			return TRUE;
		}
		$ip = gethostbyname($host);
		if ($ip == $host) {
			return FALSE;
		} else {
			return TRUE;
		}
	} else {
		return FALSE;
	}
}

function parse_mc_servers($servers, $arg) {
/* 	parse_mc_servers()
	@params mixed $servers runtime argument array
	@params string $arg variable name that contains server list setting
*/
	global $settings;

	check_arg($servers, $arg);

	if (isset($servers[$arg])) {
		$count = 0;
		if (ereg(",", $servers[$arg])) {
			$split_options = split(',', $servers[$arg]);
			foreach ($split_options as $current) {
				$settings['memcache'][$count]['server'] = 'localhost';
				$settings['memcache'][$count]['tcp_port'] = '11211';
				$settings['memcache'][$count]['udp_port'] = '0';
				if (ereg(":", $current)) {
					$curr_explode = explode(':', $current);
					$settings['memcache'][$count]['server'] = strtolower(trim($curr_explode[0]));
					if (isset($curr_explode[1])) {
						$settings['memcache'][$count]['tcp_port'] = (int) $curr_explode[1];
					}
					if (isset($curr_explode[2])) {
						$settings['memcache'][$count]['udp_port'] = (int) $curr_explode[2];
					}
					if (!valid_host($settings['memcache'][$count]['server'])) {
						printf("Hostname: " . $settings['memcache'][$count]['server'] . " is not a valid hostname!\n");
						exit(1);
					}
				} else {
					$settings['memcache'][$count]['server'] = strtolower(trim($current));
					$settings['memcache'][$count]['tcp_port'] = 11211;
					$settings['memcache'][$count]['udp_port'] = 0;
					if (!valid_host($settings['memcache'][$count]['server'])) {
						printf("Hostname: " . $settings['memcache'][$count]['server'] . " is not a valid hostname!\n");
						exit(1);
					}
				}
				$count++;
			}
		} else {
			$settings['memcache'][0]['server'] = 'localhost';
			$settings['memcache'][0]['tcp_port'] = '11211';
			$settings['memcache'][0]['udp_port'] = '0';
			if (ereg(":", $servers[$arg])) {
				$curr_explode = explode(':', $servers[$arg]);
				$settings['memcache'][$count]['server'] = strtolower(trim($curr_explode[0]));
				if (isset($curr_explode[1])) {
					$settings['memcache'][$count]['tcp_port'] = (int) $curr_explode[1];
				}
				if (isset($curr_explode[2])) {
					$settings['memcache'][$count]['udp_port'] = (int) $curr_explode[2];
				}
				if (!valid_host($settings['memcache'][$count]['server'])) {
					printf("Hostname: " . $settings['memcache'][$count]['server'] . " is not a valid hostname!\n");
					exit(1);
				}
			} else {
				$settings['memcache'][$count]['server'] = strtolower(trim($servers[$arg]));
				if (isset($curr_explode[1])) {
					$settings['memcache'][$count]['tcp_port'] = 11211;
				}
				if (isset($curr_explode[1])) {
					$settings['memcache'][$count]['udp_port'] = 0;
				}
				if (!valid_host($settings['memcache'][$count]['server'])) {
					printf("Hostname: " . $settings['memcache'][$count]['server'] . " is not a valid hostname!\n");
					exit(1);
				}
			}
		}
	}
}

function parse_collector($collector, $arg) {
/* 	parse_collector()
	@params mixed $collector runtime argument array
	@params string $arg variable name that contains collector setting
*/
	global $settings;

	check_arg($collector, $arg);

	$host_info = posix_uname();
	$nodename = $host_info['nodename'];
	if (eregi('.', $nodename)) {
		$e_nodename = explode(".", $nodename);
		$hostname = $e_nodename[0];
	} else {
		$hostname = $nodename;
	}


	$settings['collector']['server'] = $hostname;
	$settings['collector']['port'] = '9091';
	if (isset($collector[$arg])) {
		$e_collector = explode(':', $collector[$arg]);
		$settings['collector']['server'] = strtolower(trim($e_collector[0]));
		if (!valid_host($settings['collector']['server'])) {
			printf("Hostname: " . $settings['collector']['server'] . " is not a valid hostname!\n");
			exit(1);
		}
		if (isset($e_collector[1])) {
			$settings['collector']['port'] = (int) $e_collector[1];
		} else {
			$settings['collector']['port'] = 9091;
		}
	}
}

function parse_access_pattern($pattern, $arg) {
 /*	parse_access_pattern()
	@params mixed $pattern runtime argument array
	@params string $arg variable name that contains access pattern setting
*/
	global $settings;

	check_arg($pattern, $arg);

	$settings['set_pattern'] = "S";
	$settings['get_pattern'] = "R";
	if (isset($pattern[$arg])) {
		$exploded_option = explode(':', $pattern[$arg]);
		switch ($exploded_option[0]) {
			case "R":
				$settings['set_pattern'] = "R";
			break;
			case "r":
				$settings['set_pattern'] = "R";
			break;
			case "S":
				$settings['set_pattern'] = "S";
			break;
			case "s":
				$settings['set_pattern'] = "S";
			break;
			default:
				printf("Invalid access pattern!\n");
				exit(1);
			break;
		}
		switch ($exploded_option[1]) {
			case "R":
				$settings['get_pattern'] = "R";
			break;
			case "r":
				$settings['get_pattern'] = "R";
			break;
			case "S":
				$settings['get_pattern'] = "S";
			break;
			case "s":
				$settings['get_pattern'] = "S";
			break;
			default:
				printf("Invalid access pattern!\n");
				exit(1);
			break;
		}
	}
}
function parse_ratio($ratio, $arg) {
/* 	parse_ratio()
	@params mixed $ratio runtime argument array
	@params string $arg variable name that contains ratio setting
*/
	global $settings;

	check_arg($ratio, $arg);

	$settings['set_ratio'] = 1;
	$settings['get_ratio'] = 10;
	if (isset($ratio[$arg])) {
		$exploded_option = explode(':', $ratio[$arg]);
		$settings['set_ratio'] = (int) $exploded_option[0];
		$settings['get_ratio'] = (int) $exploded_option[1];
		if ($settings['set_ratio'] < 0 || $settings['set_ratio'] > 10000) {
			print("Error setting set ratio to: " . $settings['set_ratio'] . ", valid ratio: 0-10000\n");
			exit(1);
		}
		if ($settings['get_ratio'] < 0 || $settings['get_ratio'] > 10000) {
			print("Error setting get ratio to: " . $settings['get_ratio'] . ", valid ratio: 0-10000\n");
			exit(1);
		}
	}
}

function parse_offset($key_offset, $arg) {
/* 	parse_offset()
	@params mixed $key_offset runtime argument array
	@params string $arg variable name that contains offset setting
*/
	global $settings;

	check_arg($key_offset, $arg);

	$settings['offset'] = 0;
	if (isset($key_offset[$arg])) {
		$settings['offset'] = (int) $key_offset[$arg]; 
	}
}

function parse_checksum($checksum, $arg) {
/* 	parse_checksum()
	@params mixed $checksum runtime argument array
	@params string $arg variable name that contains checksum setting
*/
	global $settings;

	check_arg($checksum, $arg);

	$settings['checksum'] = TRUE;
	if (isset($checksum[$arg])) {
		$settings['checksum'] = FALSE;
	}
}

function parse_prefix($prefix, $arg) {
/* 	parse_prefix()
	@params mixed $perfix runtime argument array
	@params string $arg variable name that contains prefix setting
*/
	global $settings;

	check_arg($prefix, $arg);

	$settings['prefix'] = 'brutis-';
	if (isset($prefix[$arg])) {
		$settings['prefix'] = trim($prefix[$arg]);
		if (eregi(" ",$settings['prefix'])) {
			print("Error, can not have spaces in key prefix!\n");
		}
	}
}

function parse_runtime($runtime, $arg) {
/* 	parse_runtime()
	@params mixed $servers runtime argument array
	@params string $arg variable name that contains runtime setting
*/
	global $settings;

	check_arg($runtime, $arg);

	$settings['runtime'] = NULL;
	if (isset($runtime[$arg])) {
		$settings['runtime'] = (int) $runtime[$arg];
		if ($settings['runtime'] < 1) {
			print("Error setting runtime to: " . $settings['runtime'] . ", runtime must be greater then 0!\n");
			exit(1);
		}
	}
}

function parse_forks($options, $arg) {
/* 	parse_forks()
	@params mixed $options runtime argument array
	@params string $arg variable name that contains forks setting
*/
	global $settings;

	check_arg($options, $arg);

	$settings['forks'] = 1;
	if (isset($options[$arg])) {
		$settings['forks'] = (int) $options[$arg];
		if ($settings['forks'] > 50 || $settings['forks'] < 1) {
			print("Error setting forks to: " . $settings['forks'] . ", must be between 1-50!\n");
			exit(1);
		}
	}
}

function parse_output($options, $arg) {
/* 	parse_output()
	@params mixed $options runtime argument array
	@params string $arg variable name that contains output filename setting
*/
	global $settings;

	check_arg($options, $arg);

	$settings['filename'] = NULL;
	if (isset($options[$arg])) {
		$settings['filename'] = trim($options[$arg]);
	}
}


function parse_operations($operations, $arg) {
/* 	parse_operations()
	@params mixed $operations runtime argument array
	@params string $arg variable name that contains number of operations setting
*/
	global $settings;

	check_arg($operations, $arg);

	$settings['operations'] = NULL;
	if (isset($operations[$arg])) {
		$settings['operations'] = (int) $operations[$arg];
		if ($settings['operations'] < 1) {
			print("Error setting operations to: " . $settings['operations'] . ", runtime must be greater then 0!\n");
			exit(1);
		}
	}
}

function parse_keys($keys, $arg) {
/* 	parse_keys()
	@params mixed $keys runtime argument array
	@params string $arg variable name that contains number of keys setting 
*/
	global $settings;

	check_arg($keys, $arg);

	$settings['max_keys'] = 1000000;
	if (isset($keys[$arg])) {
		$settings['max_keys'] = (int) $keys[$arg];
		if ($settings['max_keys'] < 1 || $settings['max_keys'] > 4294967295) {
			print("Error setting max_keys to: " . $settings['max_keys'] . ", valid max_keys: 1-4294967295\n");
			exit(1);
		}
	}
}

function parse_object_size($object_size, $arg) {
/* 	parse_object_size()
	@params mixed $object_size runtime argument array
	@params string $arg variable name that contains object size setting
*/
	global $settings;

	check_arg($object_size, $arg);

	$settings['object_size'] = 256;
	if (isset($object_size[$arg])) {
		$settings['object_size'] = (int) $object_size[$arg];
		if ($settings['object_size'] < 1 || $settings['object_size'] > 33554432) {
			print("Error setting object_size to: " . $settings['object_size'] . ", valid sizes: 1-33554432\n");
			exit(1);
		}
		if ($settings['object_size'] < 33 && $settings['checksum'] == TRUE) {
			$settings['checksum'] = FALSE;
			printf("\nWarning, Can not do MD5 checksum on objects smaller then 33 bytes. Disabling MD5 checksums!\n");
		}
	}
}

function parse_batch($batch, $arg) {
/* 	parse_batch()
	@params mixed $batch runtime argument array
	@params string $arg variable name that contains batch setting 
*/
	global $settings;

	check_arg($batch, $arg);

	$settings['batch'] = 1;
	if (isset($batch[$arg])) {
		$settings['batch'] = (int) $batch[$arg];
		if ($settings['batch'] <= 0) {
			printf("Error setting batch to " . $settings['batch'] . ", Must be > 0!\n");
			exit(1);
		}
	}
}

function parse_poll($poll, $arg) {
/* 	parse_poll()
	@params mixed $poll runtime argument array
	@params string $arg variable name that contains poll setting 
*/
	global $settings;

	check_arg($poll, $arg);

	$settings['poll'] = 2;
	if (isset($poll[$arg])) {
		$settings['poll'] = (int) $poll[$arg];
		if ($settings['poll'] < 1) { 
			printf("Error, Poll must be > 0 seconds!\n");
			exit(1);
		}
	}
}

function parse_verbose($verbose, $arg) {
/* 	parse_verbose()
	@params mixed $verbose runtime argument array
	@params string $arg variable name that contains verbose setting 
*/
	global $settings;

	check_arg($verbose, $arg);

	$settings['verbose'] = FALSE;
	if (isset($verbose[$arg])) {
		$settings['verbose'] = TRUE;
	}
}

?>
