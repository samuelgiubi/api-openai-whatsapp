<?php
// Iniciamos libreria de Twilio para recepción/envío vía WhatsApp
require_once 'vendor/autoload.php';
use Twilio\Rest\Client;

// Utilizar constantes para las cadenas de comando
define("COMMAND_TEXT", "#texto");
define("COMMAND_IMAGE", "#imagen");

// Utilizar variables de entorno para las credenciales
$openaiAPI = getenv("OPENAI_API_KEY");
$twilio_sid = getenv("TWILIO_SID");
$twilio_token = getenv("TWILIO_TOKEN");
$numero_whatsapp = getenv("NUMERO_WHATSAPP");

// Recibimos el $_POST del número de teléfono y el mensaje escribo al WhatsApp de nuestra cuenta Twilio
$from_number = filter_var($_POST['From'], FILTER_SANITIZE_STRING);
$message_body = filter_var($_POST['Body'], FILTER_SANITIZE_STRING);

// Reconocemos si vino #texto #imagen o un comando erróneo 
if(strpos($message_body, COMMAND_TEXT) === 0){
    // Registramos en un log el mensaje recibido
    file_put_contents("messages.log", "From: $from_number\nMessage: $message_body\n\n", FILE_APPEND);
    // Eliminamos el COMMAND_TEXT inicial y recuperamos el mensaje escrito
    $requested_value = trim(substr($message_body, strlen(COMMAND_TEXT)));
    // Envíamos a ChatGPT del mensaje escrito
    $response = handleResponse($requested_value, "text-davinci-003", ["Human:"," AI:"]);
    header("content-type: text/xml");
    echo "<?xml version='1.0' encoding='UTF-8'?>\n";
    echo "<Response>\n";
    echo "  <Message>$response</Message>\n";
    echo "</Response>\n";

}else if(strpos($message_body, COMMAND_IMAGE) === 0){
    // Registramos en un log el mensaje recibido
    file_put_contents("messages.log", "From: $from_number\nMessage: $message_body\n\n", FILE_APPEND);
    // Eliminamos el COMMAND_IMAGE inicial y recuperamos el mensaje escrito
    $requested_value = trim(substr($message_body, strlen(COMMAND_IMAGE)));
    // Envíamos a Dall-e del mensaje escrito
    $response = handleResponse($requested_value, "image-alpha-001", [], "images/generations", ["size" => "1024x1024", "num_images" => 1]);
    $client = new Client($twilio_sid, $twilio_token);
    try {
        $message = $client->messages->create(
            $from_number,
            array(
                "from" => "whatsapp:$numero_whatsapp",
                "body" => $message,
                "mediaUrl" => $response
            )
        );
    } catch (Exception $e) { 
        file_put_contents("messages.log", "From: $from_number\nError Message: $e->getMessage()\n\n", FILE_APPEND); 
    }
} else {
    header("content-type: text/xml");
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
    echo "<Response>\n";
    echo "  <Message>Error: me tenes que enviar con un *'#imagen'* o *'#texto'* lo que queres que haga, por ejemplo: 
    
    *#texto generar una frase de motivación.* 
    
    o bien 

    *#imagen generar la imagen de un gato.*</Message>\n";
    echo "</Response>\n";
}

function handleResponse($prompt, $model, $stop = [], $endpoint = "completions", $extra_data = []){
    try {
        $data = [
            "model" => $model,
            "prompt" => $prompt,
            "temperature" => 0.9,
            "max_tokens" => 1000,
            "top_p" => 1,
            "frequency_penalty" => 0,
            "presence_penalty" => 0.6,
            "stop" => $stop
        ];
        $data = array_merge($data, $extra_data);
        $url = "https://api.openai.com/v1/$endpoint";
        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer ".getenv("OPENAI_API_KEY")
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        $response_array = json_decode($response, true);
        if ($endpoint === "images/generations") {
            return $response_array['data'][0]['url'];
        } else {
            return $response_array->choices[0]->text;
        }
    } catch (Exception $e) {
        file_put_contents("messages.log", "Error Message: $e->getMessage()\n\n", FILE_APPEND);
        throw $e;
    }
}
?>
