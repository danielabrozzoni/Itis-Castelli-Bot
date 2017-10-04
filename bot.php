<?php
	
	$conn = dbConnect ();

	setlocale(LC_ALL, 'it_IT');

	//ini_set('max_execution_time', 500);
	ignore_user_abort(true);

	//Load the HTML page
	@$url = "http://www.iiscastelli.gov.it/ViewDocN.aspx?ftype=$FTYPE$&fltr=&section=doc&lock=no&shownav=yes&showlog=$SHOWLOG$";
	$html = file_get_contents($url);

	//Create a new DOM document
	$dom = new DOMDocument;

	@$dom->loadHTML($html);
 
	$links = $dom->getElementsByTagName('a');

	$reversedArray = createReverseArray ($links);

	$count = count ($reversedArray);

	for($i = 0; $i < $count; $i+=1) {
		$title = $reversedArray[$i][0];
		$titleNormal = str_replace("'","",$title);
		$pdfUrl = "http://www.iiscastelli.gov.it/" . $reversedArray[$i][1];
		$pdfUrlNormal = str_replace("'","",$pdfUrl);

		if(!getData($titleNormal, $conn)) {

    			$messageId = sendTitleMessage ($title);

			sleep (1);

    			sendPdfMessage ($messageId, $pdfUrl);

			sleep (1);

			do{
				$result = setData ($titleNormal, $pdfUrlNormal, $conn);
				sleep(0.5);
			} while(!$result);
		}
		
	}

	mysqli_close($conn);

	//Inverto l'array di coppie titolo/url in modo tale che parta dalla circolare più vecchia
	function createReverseArray ($ogg) {
		$array = array();
		$i = 0;

		foreach ($ogg as $element) {
			$array[$i] = array($element->nodeValue, $element->getAttribute('href'));
			$i += 1;
		}

		$arrayReverse = array_reverse($array);

		return $arrayReverse;
	}

	//mi collego al db
	function dbConnect () {
		$servername = "localhost";
        $username = username_database;
        $password = password_database;
        $dbname = name_database;

        // Create connection
        $conn = mysqli_connect($servername, $username, $password, $dbname);

        // Check connection
        if (!$conn) {
            die("Connessione fallita: " . mysqli_connect_error() . "</br>");
            exit;
        }
        echo "<b>Connesso con successo</b></br>";

        return $conn;
	}

	//Inserisco i dati titolo/url nel db
	function setData ($title, $pdfUrl, $conn) {
		$sql = "INSERT INTO `circolari`(`title`, `url`) VALUES ('$title','$pdfUrl')";
		if (mysqli_query($conn, $sql)) {
    			echo "<b>Voce registrata con successo!</b></br>";
			return true;
		}
		else {
    			echo "Errore: " . $sql . mysqli_error($conn) . "</br>";
			return false;
		}
	}

	//controllo se una circolare e' nel database
	function getData ($title, $conn) {
		$sql = "SELECT `id` FROM `circolari` WHERE `title` = '$title'";
		$result = mysqli_query($conn, $sql);
		if ($result === false)
			exit;
		else if (mysqli_num_rows($result) > 0)
			$sent = true;
		else
			$sent = false;

		return $sent;
	}

	function sendTitleMessage ($title) {
		$data = strftime("%e %B %Y");
		$array = array("chat_id" => chat_id, "parse_mode" => "Markdown", "text" => "*Nuova circolare del $data!*\r\n`$title`", "disable_notification" => true);
		$jsonArray = json_encode($array);
		$ch = curl_init('https://api.telegram.org/botTOKEN/sendMessage');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$response = curl_exec($ch);
		curl_close($ch);
		if($response === false)
			exit;
		//echo $response . "</br>";
		$array = json_decode($response, true);
		$messageId = $array ["result"] ["message_id"];
		return $messageId;
	}

	function sendPdfMessage ($messageId, $pdfUrl) {
		$array = array("chat_id" => chat_id, "reply_to_message_id" => $messageId, "document" => $pdfUrl, "disable_notification" => true);
		$jsonArray = json_encode($array);
		$ch = curl_init('https://api.telegram.org/botTOKEN/sendDocument');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$response = curl_exec($ch);
		curl_close($ch);
		if($response === false) {
			$array = array("chat_id" => "@ItisCastelli", "message_id" => $messageId);
			$jsonArray = json_encode($array);
			$ch = curl_init('https://api.telegram.org/botTOKEN/deleteMessage');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			$response = curl_exec($ch);
			curl_close($ch);
			echo "errore invio PDF";
			exit;
		}
		//echo "</br>";
	} 
?>
