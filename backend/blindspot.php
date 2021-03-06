<?php 
   session_start();

	include_once'../config/init.php';
	// include_once './scws.php';
	class register{

		public $_config;
		/**
		*	Constructor
		*/
		function __counstrut($_config = ''){

			/**
	         * Init config
	         */
	        empty($config) ? $this->initConfig() : $this->_config = $config;
	        
	        /**
	         * Init database link
	         */
	        $this->_initDb();
		}


	    public function getinfo(){

	    	if(!isset($_POST['func'])){
	    		header("Location:../profile.php");
	    	}else{
		    	$func = $_POST['func'];
	    	}

	    	switch ($func) {

	    		case 'insert_member': //如果曾經註冊過，就回傳1
	    			try{
		    			$f_id = $_POST['facebook_id'];
		    			$l_name = $_POST['last_name'];
		    			$f_name = $_POST['first_name'];
		    			$email = isset($_POST['email']) ? $_POST['email'] : "";	    				
		    			$gender = isset($_POST['gender']) ? $_POST['gender'] : "";	    				
		    			$birth = isset($_POST['birthday']) ? $_POST['birthday'] : "";	    				
	    			}catch(Exception $e){
	    				return "fail";
	    			}
	    			// print_r($_POST);
	    			//判斷是否有登入過
	    			$sql = "SELECT m_id FROM `member` WHERE fb_id = $f_id";
	    			$result = $this->db_query($sql);
	    			if(!isset($result[0]['m_id']) || empty($result[0]['m_id'])){

		    			//如果第一次註冊就新增一個會員，不然就跳過
		    			$sql = "INSERT IGNORE INTO `member`(`fb_id`, `l_name`, `f_name`, `birthday`, `gender`, `email`, `status`) 
		    								VALUES ('$f_id', '$l_name', '$f_name', '$birth', '$gender', '$email', 'login')";
		    			$insert_result = $this->db_exec($sql);


		    			//把會員id記到SESSION
		    			$sql = "SELECT m_id FROM `member` WHERE fb_id = $f_id";
		    			$result = $this->db_query($sql);
		    			$_SESSION['user_id'] = $result[0]['m_id'];
		    			$user_id = $_SESSION['user_id'];

		    			//create會員資料夾
		    			$source_dir = "../images/profile/0";
		    			$destination_dir = "../images/profile/$user_id";
						$this->recurse_copy($source_dir, $destination_dir);

		    			//下載會員的大頭照
		    			$download_url = "http://graph.facebook.com/$f_id/picture?type=large";
						$save_route = "../images/profile/$user_id/sticker.png";
						file_put_contents($save_route, file_get_contents($download_url));
		    			
		    			//下載會員的封面照

	    			}else{
		    			//把會員id記到SESSION
		    			$_SESSION['user_id'] = $result[0]['m_id'];
		    			$user_id = $_SESSION['user_id'];

	    				//更新會員資料
	    				$sql = "UPDATE `member` 
	    						SET `birthday` = '$birth', `gender` = '$gender', `email` = '$email'
	    						WHERE m_id = $user_id";
	    				$result = $this->db_exec($sql);
	    			}

	    			$this->save_log($user_id, 'login');
	    			return "success";

	    			break;

	    		case 'if_login'://剛進首頁先判斷是不是有登入過，有就轉跳到profile.php
	    			if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
	    				$result['status'] = "notlogin";
		    			return json_encode($result);
	    			}else{
	    				$result['status'] = "login";
		    			return json_encode($result);
	    			}
	    			break;

	    		case 'logout':
	    			$user_id = $_SESSION['user_id'];
	    			$_SESSION['user_id']='';
	    			$result['status'] = "success";

	    			$this->save_log($user_id, 'logout');

	    			return json_encode($result);	
	    			break; 

	    		case 'search_name':
	    			$search_name = $_POST['input'];
	    			$sql = "SELECT `m_id`, `l_name`, `f_name`
	    					FROM `member`
	    					WHERE (l_name like '%$search_name%' or f_name like '%$search_name%')";
	    			$search_result = $this->db_query($sql);
	    			
	    			if (!isset($search_result[0]['m_id']) || empty($search_result[0]['m_id'])) {
		    			$result['status'] = "fail";  //判斷fail，不回傳值	
		    			return json_encode($result);
	    			}else{
	    				$result['status'] = "success";
		    			$result['data'] = $search_result;
		    			return json_encode($result);
	    			}
	    			break;

	    		case 'get_personal_info':

	    			$result = array();
	    			try{
		    			$friend_id = $_POST['friend_id'];
	    			}catch(Exception $e){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}

	    			if($friend_id == 'me' || $friend_id == $_SESSION['user_id']){//如果是接到me
	    				if(!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])){//現在沒有登入
		    				$result['status'] = "notlogin";
		    				return json_encode($result);
	    				}else{//個人看自己的首頁
		    				$friend_id = $_SESSION['user_id'];
		    				$result['status'] = "me";
	    				}
	    			}else if(empty($friend_id)){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}else{
		    			$result['status'] = "friend";
	    			}


	    			$sql = "SELECT `m_id`, `l_name`, `f_name`, `intro` FROM `member` WHERE m_id = $friend_id";
	    			$q_result = $this->db_query($sql);
	    			if(sizeof($q_result) > 0){
	    				$q_result[0]['intro'] = htmlspecialchars_decode($q_result[0]['intro']);
		    			$result['data'] = $q_result;
		    			return json_encode($result);
	    			}else{
		    			$result['status'] = "invaliduser";
		    			return json_encode($result);
	    			}

	    		break;

	    		case 'save_personal_info':

	    			try{
	    				$l_name = $_POST['lname'];
	    				$l_name = strip_tags($l_name);
	    				$l_name = htmlspecialchars($l_name);
                    	$l_name = addslashes($l_name);

		    			$f_name = $_POST['fname'];
		    			$f_name = strip_tags($f_name);
		    			$f_name = htmlspecialchars($f_name);
		    			$f_name = addslashes($f_name);
		    			
		    			$intro = $_POST['introduction'];
		    			$intro = strip_tags($intro);
		    			$intro = htmlspecialchars($intro);
		    			$intro = addslashes($intro);

		    		}catch(Exception $e){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}
					
	    			$user = $_SESSION['user_id'];
					$sql = "UPDATE `member`
							SET l_name = '$l_name', f_name = '$f_name', intro = '$intro'
							WHERE m_id = $user";
	    			$change_result = $this->db_exec($sql);
	    			$result['status'] = "success";
	    			return json_encode($result);	# code...
	    			break;

	    		case 'post_on_wall':

	    			try{
	    				$content = $_POST['content'];
	    				// $content = strip_tags($content);
	    				$content = htmlspecialchars($content);
	    				$content = nl2br($content);
		    			$content = addslashes($content);
		    			$friend_id = $_POST['friend_id'];

		    		}catch(Exception $e){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}
					
	    			$user = $_SESSION['user_id'];
					$sql = "INSERT INTO `post`(p_content,receiverid,senderid)
							VALUES ('$content', '$friend_id', '$user')";
	    			$change_result = $this->db_exec($sql);
	    			$sql = "SELECT `pid`,`p_content`,`senderid`, p.updatetime, m.l_name, m.f_name, IFNULL(a.count_comment, 0) as count_comment
	    					FROM `post` p
	    					LEFT JOIN `member` m
	    						on p.senderid = m.m_id
	    					LEFT JOIN (
	    						SELECT COUNT(c_id) as count_comment, p_id FROM `comment` GROUP BY p_id
	    						) a
								on p.pid = a.p_id
	    					ORDER BY pid DESC
	    					LIMIT 0,1 ";
	    			$q_result = $this->db_query($sql);
	    			$result['status'] = 'success';
	    			$result['data'] = $q_result;


	    			$this->save_log($user, 'post_on_wall', $friend_id);
	    			//重新整理接受po文人的關鍵字
				    // $scws = new simpleCSWS();
				    // $keyword = $scws->getoneskeyword($friend_id);
				    // $sql = "UPDATE `member` SET `keyword` = '$keyword' WHERE `m_id` = $friend_id";
				    // $key_exec = $this->db_exec($sql);
	    			return json_encode($result);
	    			break;

	    		case 'get_post':

	    			try{
	    				$friend_id = $_POST['friend_id'];
	    				$user_id = $_SESSION['user_id'];
		    		}catch(Exception $e){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}
					
	    			$sql = "SELECT `pid`,`p_content`,`senderid`, p.updatetime, m.l_name, m.f_name, IFNULL(a.count_comment, 0) as count_comment
	    					FROM `post` p
	    					LEFT JOIN `member` m
	    						on p.senderid = m.m_id
	    					LEFT JOIN (
	    						SELECT COUNT(c_id) as count_comment, p_id FROM `comment` where able=1 GROUP BY p_id
	    						) a
								on p.pid = a.p_id

	    					WHERE receiverid = '$friend_id'
	    					and able = '1'
	    					ORDER BY p.updatetime DESC";
	    			$q_result = $this->db_query($sql);
	    			$result['status'] = 'success';
    				// $q_result[0]['p_content'] = htmlspecialchars_decode($q_result[0]['p_content']);
	    			$result['data'] = $q_result;
	    			$result['delete_able'] = $user_id;
	    			return json_encode($result);	# code...
	    			break;

	    		case 'comment_on_post':

	    			try{
	    				$c_content = $_POST['c_content'];//<script alert("159");</script
		    			// $c_content = strip_tags($c_content);//alert("159")
		    			$c_content = htmlspecialchars($c_content);//&ltscript&gtalert(&quot159&quot);&lt/script&gt 
	    				$c_content = nl2br($c_content);
		    			$p_id = $_POST['p_id'];

		    		}catch(Exception $e){
	    				$result['status'] = "fail";
	    				return json_encode($result);
	    			}
					
	    			$user = $_SESSION['user_id'];
					$sql = "INSERT INTO `comment`(c_content,p_id,sender_id)
							VALUES ('$c_content', '$p_id', '$user')";
	    			$change_result = $this->db_exec($sql);
					$sql = "SELECT `sender_id`,`c_content`,`hate`,`love`,m.l_name, m.f_name,`c_id`, c.updatetime
	    					FROM `comment` c
	    					LEFT JOIN `member` m
	    						on c.sender_id = m.m_id
	    					WHERE p_id = '$p_id'
	    					ORDER BY `c_id` DESC LIMIT 1";
	    			$q_result = $this -> db_query($sql);
	    			$result['status'] = 'success';
	    			$result['data'] = $q_result;

	    			//通知原po
	    			$ori_poster_sql = "SELECT `senderid` FROM `post` WHERE pid = $p_id";
	    			$ori_poster_result = $this->db_query($ori_poster_sql);
	    			$ori_poster = $ori_poster_result[0]['senderid'];
	    			$notification_sql = "";
	    			if($ori_poster != $user){//原po是自己就不用通知
		    			$notification_sql = "INSERT INTO `notification` (`m_id`, `target_id`, `p_id`, `status`)
		    									values ('$user', '$ori_poster', '$p_id', 1)
		    								ON DUPLICATE KEY UPDATE updatetime = CURRENT_TIMESTAMP; ";
	    			}

	    			//通知在上面comment的人
	    			$commenter_sql = "SELECT DISTINCT `sender_id` FROM `comment` WHERE p_id = $p_id";
	    			$commenter_result = $this->db_query($commenter_sql);
	    			foreach ($commenter_result as $key => $value) {
	    				if($value['sender_id'] != $user){//自己不用通知
		    				$notification_sql .= "INSERT INTO `notification` (`m_id`, `target_id`, `p_id`, `status`)
		    										values ('$user', '".$value['sender_id']."', '$p_id', 1)
		    										ON DUPLICATE KEY UPDATE updatetime = CURRENT_TIMESTAMP; ";
	    				}
	    			}
	    			if(isset($notification_sql) && !empty($notification_sql)){
		    			$notification_result = $this->db_exec($notification_sql);
	    			}
	    			return json_encode($result);
	    			break;
	    	

				case 'love_post':
					try{
						$p_id = $_POST['p_id'];
						$love = $_POST['action'];
					} 
					catch(Exception $e){
						$result['status'] = "fail";
						return json_encode($result);
					}

					$user_id = $_SESSION['user_id'];

					if($love == 'love'){
						$sql = "SELECT `status` FROM `member_post` WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						$q_result = $this->db_query($sql);

						if(!isset($q_result[0]) || $q_result[0]['status'] != '1'){//原本沒有按過love的人才會進來

							if(isset($q_result[0]) && $q_result[0]['status'] == '2'){//原本是按hate的人，要先把hate-1
								$minus_sql = "UPDATE `post` SET `hate` = `hate` -1 WHERE pid = $p_id ";
								$minus_result = $this->db_exec($minus_sql);
							}

							$sql = "UPDATE `post`
									SET `love` = `love` + 1
									WHERE `pid` = $p_id;
									INSERT INTO `member_post` (`m_id`,`p_id`,`status`)
									VALUES ($user_id, $p_id , 1)
									ON DUPLICATE KEY UPDATE `status` = 1";
						}

					}

					else if ($love == 'hate') {

						$sql = "SELECT `status` FROM `member_post` WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						$q_result = $this->db_query($sql);

						if(!isset($q_result[0]) || $q_result[0]['status'] != '2'){//原本沒有按過hate的人才會進來

							if(isset($q_result[0]) && $q_result[0]['status'] == '1'){//原本是按love的人，要先把love-1
								$minus_sql = "UPDATE `post` SET `love` = `love` -1 WHERE pid = $p_id ";
								$minus_result = $this->db_exec($minus_sql);
							}

							$sql = "UPDATE `post`
									SET `hate` = `hate` + 1
									WHERE `pid` = $p_id;
									INSERT INTO `member_post` (`m_id`,`p_id`,`status`)
									VALUES ($user_id, $p_id , 2)
									ON DUPLICATE KEY UPDATE `status` = 2";
						}

					}
					else if ($love == 'love_cancel'){
						$sql = "SELECT `status` FROM `member_post` WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						$q_result = $this->db_query($sql);
						if (isset($q_result[0]) && $q_result[0]['status'] == 1){//如果原本就是按love
							$sql = "UPDATE `post`
									SET `love` = `love` - 1
									WHERE `pid` = $p_id;
									UPDATE `member_post`
									SET `status` = 0
									WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						}
						else if (isset($q_result[0]) && $q_result[0]['status'] == 2){
							$sql = "UPDATE `post`
									SET `hate` = `hate` - 1
									WHERE `pid` = $p_id;
									UPDATE `member_post`
									SET `status` = 0
									WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						}	
						else{//status == 0 || status == 2 || status == null甚麼事情都不做
							$sql = "SELECT `ilovesteven`";
						}	
					}
					else if ($love == 'hate_cancel'){
						$sql = "SELECT `status` FROM `member_post` WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						$q_result = $this->db_query($sql);
						if(isset($q_result[0]) && $q_result[0]['status'] == 2){//如果原本就是按hate
							$sql = "UPDATE `post`
									SET `hate` = `hate` - 1
									WHERE `pid` = $p_id;
									UPDATE `member_post`
									SET `status` = 0
									WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						}
						else if (isset($q_result[0]) && $q_result[0]['status'] == 1){
							$sql = "UPDATE `post`
									SET `love` = `love` - 1
									WHERE `pid` = $p_id;
									UPDATE `member_post`
									SET `status` = 0
									WHERE `m_id` = $user_id AND `p_id` = $p_id ";
						}
						else{//status == 0 || status == null甚麼事情都不做
							$sql = "SELECT `ilovesteven`";
						}
					}
					else{
						$result['status'] = "fail";
						return json_encode($result);
					}
					$change_result = $this->db_exec($sql);
					$result['status'] = "success";
	    			return json_encode($result);
				break;

				
				case 'upload_photo':

					try {
						$files = $_FILES;
		    			$user_id = $_SESSION['user_id'];
					} catch (Exception $e) {
						$result['status'] = "fail";
						return json_encode($result);
					}

					foreach ($files as $key => $value) {

						if(!empty($value['tmp_name'])){
			    			$temp_name = $value['tmp_name'];
			    			$photo_name = $key;

							$output_size = array(
								$photo_name => array( 'output_w' => 200, 'output_h' => 200 )
							);					

							if($photo_name == 'sticker'){
								$output_size[$photo_name]['output_w'] = 200;
								$output_size[$photo_name]['output_h'] = 200;
								$this->resize_photo($user_id, $output_size, $photo_name);
							}else if($photo_name == 'back_photo'){
								$output_size[$photo_name]['output_w'] = 1280;
								$output_size[$photo_name]['output_h'] = 800;
								$this->resize_photo($user_id, $output_size, $photo_name);
							}
						}
					}
					header('Location:../profile.php');
					$result['status'] = "success";
	    			return json_encode($result);
				break;

				case 'get_notification':

					$user_id = $_SESSION['user_id'];
					$sql = "SELECT a.`m_id`, c.f_name as m_id_fname,c.l_name as m_id_lname  ,
									a.`target_id`, a.`p_id`, b.senderid as ori_poster, 
									b.receiverid as wall_user,d.f_name as wall_user_fname,d.l_name as wall_user_lname,
									a.updatetime, a.status
							FROM `notification` a
							LEFT JOIN `post` b
								on a.p_id = b.pid
							LEFT JOIN `member` c
								on a.m_id = c.m_id
							LEFT JOIN `member` d
								on b.receiverid = d.m_id
							WHERE a.target_id = $user_id
							ORDER BY  a.updatetime DESC";
					$noti_result = $this->db_query($sql);
					$result['status'] = "success";
					$result['data'] = $noti_result;
	    			return json_encode($result);

				break;

				case 'seen_notification':
					try {
		    			$user_id = $_SESSION['user_id'];
		    			$m_id = $_POST['m_id'];
		    			$p_id = $_POST['p_id'];
					} catch (Exception $e) {
						$result['status'] = "fail";
						return json_encode($result);
					}
					$sql = "UPDATE `notification`
							SET status = -1
							WHERE m_id = $m_id
							AND target_id = $user_id
							AND p_id = $p_id";
					$result = $this->db_exec($sql);
						$result = array();
						$result['status'] = "success";
						return json_encode($result);
				break;
	    		default:
	    			# code...
	    			break;
	    	}
	    }

		function recurse_copy($src,$dst) { 
		    $dir = opendir($src); 
		    @mkdir($dst); 
		    while(false !== ( $file = readdir($dir)) ) { 
		        if (( $file != '.' ) && ( $file != '..' )) { 
		            if ( is_dir($src . '/' . $file) ) { 
		                recurse_copy($src . '/' . $file,$dst . '/' . $file); 
		            } 
		            else { 
		            	if($file != 'back_photo.png')
			                copy($src . '/' . $file,$dst . '/' . $file); 
		            } 
		        } 
		    } 
		    closedir($dir); 
		} 

		function resize_photo($id, $output_size , $photo_name){
			if( isset($_FILES[$photo_name] ) ) {
				if( is_uploaded_file( $_FILES[$photo_name]['tmp_name'] ) ) {
					$fileSource = $_FILES[$photo_name];
					$fileExt = $fileSource['type'];

					if($fileExt == 'image/jpeg'){
						$fileType=".jpg";
					}elseif($fileExt == 'image/gif'){
						$fileType=".gif";
					}elseif($fileExt == 'image/png'){
						$fileType=".png";
					}else{
						exit;
					}

					if($fileType == ".jpg"){
						$src = imagecreatefromjpeg($fileSource['tmp_name']);
					}elseif($fileType == ".gif"){
						$src = imagecreatefromgif($fileSource['tmp_name']);
					}elseif($fileType == ".png"){
						$src = imagecreatefrompng($fileSource['tmp_name']);
					}

					// 阻止直行圖片自動轉向
					$exif = @exif_read_data( $fileSource['tmp_name'] );
					if( !empty( $exif['Orientation'] ) ) {
						switch($exif['Orientation']) {
							case 3:
								$src = imagerotate( $src, 180, 0 );
								break;
							case 6:
								$src = imagerotate( $src, -90, 0 );
								break;
							case 8:
								$src = imagerotate( $src, 90, 0 );
								break;
							default:
								break;
						}
					}

					//取得來源圖片長寬
					$src_w = imagesx($src);
					$src_h = imagesy($src);

					foreach ($output_size as $dir_key => $dir_value) {
						$photoDir = "../images/profile/$id";

						$output_w = $dir_value['output_w'];
						$output_h = $dir_value['output_h'];

						$src_ratio = $src_w / $src_h;
						$output_ratio = $output_w / $output_h;

						if ( $src_ratio > $output_ratio ){
							// Triggered when source image is wider
							$temp_height = $output_h;
							$temp_width = ( int ) ( $output_h * $src_ratio );
						} else {
							// Triggered otherwise (i.e. source image is similar or taller)
							$temp_width = $output_w;
							$temp_height = ( int ) ( $output_w / $src_ratio );
						}
						
						// Resize the image into a temporary GD image
						$temp_gdim = imagecreatetruecolor( $temp_width, $temp_height );
						imagecopyresampled(
							$temp_gdim,
							$src,
							0, 0,
							0, 0,
							$temp_width, $temp_height,
							$src_w, $src_h
						);

						// Copy cropped region from temporary image into the desired GD image
						$x0 = ( $temp_width - $output_w ) / 2;
						$y0 = ( $temp_height - $output_h ) / 2;

						$desired_gdim = imagecreatetruecolor( $output_w, $output_h );
						imagecopy(
							$desired_gdim,
							$temp_gdim,
							0, 0,
							$x0, $y0,
							$output_w, $output_h
						);
						imagejpeg($desired_gdim, $photoDir.'/'.$dir_key.".png", 100);
					}
				}
			}
		}

	    public function db_query($sql){

			$Database = new DataBase($this->_config['host'], 
					$this->_config['username'], 
					$this->_config['password'], 
					$this->_config['dbname']);
			$Database->Connect();
			$Database->Query=$sql;
			$Database->Query();
			$result = $Database->queryResult->fetchAll();
			$Database->close();

			return $result;
	    }

	    public function db_exec($sql){
	    	
			$Database = new DataBase($this->_config['host'], 
					$this->_config['username'], 
					$this->_config['password'], 
					$this->_config['dbname']);
			$Database->Connect();
			$Database->Exec=$sql;

			if($Database->Exec()){
				$Database->close();
				return true;
			}else{
				$Database->close();
				return false;
			}

	    }
	    public function save_log($user_id,$action , $target = 0){

			//紀錄log
			$sql = "INSERT INTO `log`(`m_id`,`target` ,`action`) VALUES ('$user_id','$target' ,'$action')";
			$insert_result = $this->db_exec($sql);

	    }
	}

	$Register = new register();
	echo $Register->getinfo();

 ?>