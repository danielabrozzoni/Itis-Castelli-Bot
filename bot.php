<?php
	
	$conn = dbConnect (); //mi collego al database

	define("TOKEN","botToken"); //creo una variabile statica contenente il TOKEN

	setlocale(LC_ALL, 'it_IT'); //imposto la località per l'orario e la lingua

	ignore_user_abort(true); //continuo l'esecuzione anche se la connessione viene chiusa

	@$url = "http://www.iiscastelli.gov.it/ViewDocN.aspx?ftype=$FTYPE$&fltr=&section=doc&lock=no&shownav=yes&showlog=$SHOWLOG$";
	$html = file_get_contents($url); //carico il codice sorgente di quella pagina

	$dom = new DOMDocument;

	@$dom->loadHTML($html);
 
	$links = $dom->getElementsByTagName('a');

	$reversedArray = createReverseArray ($links); //inverto l'array

	$count = count ($reversedArray); //conto gli elementi dell'array

	for($i = 0; $i < $count; $i+=1) {
		$title = $reversedArray[$i][0];
		$titleNormal = str_replace("'","",$title); //rimuovo ' per prevenire errori durante il salvataggio dei dati nel database
		$pdfUrl = "http://www.iiscastelli.gov.it/" . $reversedArray[$i][1];
		$pdfUrlNormal = str_replace("'","",$pdfUrl);

		if(!getData($titleNormal, $conn)) { //controllo se la circolare è nel database, se non lo è:

    			$messageId = sendTitleMessage ($title); //invio il messaggio contenente il titolo della circolare

			sleep (1); //aspetto un secondo per non allertare l'antispam di Telegram

    			sendPdfMessage ($messageId, $pdfUrl); //invio il messaggio contenente il PDF

			sleep (1);

			do {
				$result = setData ($titleNormal, $pdfUrlNormal, $conn); //faccio un loop fin quando i dati non vengono salvati nel database per prevenire un doppi messaggi
				sleep(0.5); //limito le operazioni del database
			} while (!$result);
		}
	}

	mysqli_close($conn); //chiudo la connessione al database

	//FUNZIONI
	function createReverseArray ($links) { //inverto l'array di coppie titolo/url in modo tale che parta dalla circolare più vecchia
		$array = array();
		$i = 0;

		foreach ($links as $link) {
			$array[$i] = array($link->nodeValue, $link->getAttribute('href'));
			$i += 1;
		}

		$array = array_reverse($array);

		return $array;
	}

	function dbConnect () {
		$servername = host_database;
        $username = username_database;
        $password = password_database;
        $dbname = name_database;

        $conn = mysqli_connect($servername, $username, $password, $dbname);

        if (!$conn)
            die("Connessione fallita: " . mysqli_connect_error() . "</br>");

        echo "<b>Connesso con successo</b></br>";

        return $conn;
	}

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
		$ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendMessage');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$response = curl_exec($ch);
		curl_close($ch);

		if($response === false) {
			echo "Errore invio titolo";
			exit;
		}

		$array = json_decode($response, true);
		$messageId = $array ["result"] ["message_id"];

		return $messageId;
	}

	function sendPdfMessage ($messageId, $pdfUrl) {
		$array = array("chat_id" => chat_id, "reply_to_message_id" => $messageId, "document" => $pdfUrl, "disable_notification" => true);
		$jsonArray = json_encode($array);
		$ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/sendDocument');
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
		$response = curl_exec($ch);
		curl_close($ch);

		if($response === false) {
			$array = array("chat_id" => chat_id, "message_id" => $messageId);
			$jsonArray = json_encode($array);
			$ch = curl_init('https://api.telegram.org/bot' . TOKEN . '/deleteMessage');
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonArray);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
			$response = curl_exec($ch);
			curl_close($ch);
			echo "Errore invio PDF";
			exit;
		}
	} 
?>
