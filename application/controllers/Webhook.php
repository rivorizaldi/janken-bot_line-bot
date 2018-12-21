<?php defined('BASEPATH') OR exit('No direct script access allowed');

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TemplateBuilder\ButtonTemplateBuilder;
use \LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;

class Webhook extends CI_Controller {

  private $bot;
  private $events;
  private $signature;
  private $user;

  function __construct()
  {
    parent::__construct();
    $this->load->model('tebakkode_m');

    // create bot object
    $httpClient = new CurlHTTPClient($_ENV['CHANNEL_ACCESS_TOKEN']);
    $this->bot  = new LINEBot($httpClient, ['channelSecret' => $_ENV['CHANNEL_SECRET']]);
  }

  public function index()
  {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      echo "Hello Coders!";
      header('HTTP/1.1 400 Only POST method allowed');
      exit;
    }

    // get request
    $body = file_get_contents('php://input');
    $this->signature = isset($_SERVER['HTTP_X_LINE_SIGNATURE']) ? $_SERVER['HTTP_X_LINE_SIGNATURE'] : "-";
    $this->events = json_decode($body, true);

    // log every event requests
    $this->tebakkode_m->log_events($this->signature, $body);

    // debuging data
    file_put_contents('php://stderr', 'Body: '.$body);

    if(is_array($this->events['events'])){
      foreach ($this->events['events'] as $event){
        // your code here
        if(! isset($event['source']['userId'])) continue;
 
        // get user data from database
        $this->user = $this->tebakkode_m->getUser($event['source']['userId']);
 
        // if user not registered
        if(!$this->user) $this->followCallback($event);
        else {
          // respond event
          if($event['type'] == 'message'){
            if(method_exists($this, $event['message']['type'].'Message')){
              $this->{$event['message']['type'].'Message'}($event);
            }
          } else {
            if(method_exists($this, $event['type'].'Callback')){
              $this->{$event['type'].'Callback'}($event);
            }
          }
        }
      } // end of foreach
    }

  } // end of index.php

  private function followCallback($event){
    $res = $this->bot->getProfile($event['source']['userId']);
    if ($res->isSucceeded())
    {
      $profile = $res->getJSONDecodedBody();
      
      $code = array("wink" => "\u{10008F}", 
                    "Gunting" => "\u{100030}",
                    "Batu" => "\u{100031}",
                    "Kertas" => "\u{100032}");

      $opsi = ["1. Mulai Bermain","2. Panduan"];
      $length = count($opsi);

      for($i = 0; $i<$length; $i++){
        $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsi[$i]);
      } 

      // prepare button template
      $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
  
      // build message
      $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

      // create welcome message
      $message  = "Hai, " . $profile['displayName'] . "!\n";
      $message2 = "Pilih Opsi 1. Mulai Bermain, untuk memulai permainan.\n";
      $message2 .= "pilih Opsi 2. Panduan, untuk mengetahui tata cara permainan.\n";
      $message2 .= "Selamat Bermain! " . $code["wink"];
      $textMessageBuilder = new TextMessageBuilder($message);
      $textMessageBuilder2 = new TextMessageBuilder($message2);

      // create sticker message
      $stickerMessageBuilder = new StickerMessageBuilder(1, 106);

      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder);
      $multiMessageBuilder->add($stickerMessageBuilder);
      $multiMessageBuilder->add($textMessageBuilder2);
      $multiMessageBuilder->add($messageBuilder);


      // send reply message
      $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);

      // save user data
      $this->tebakkode_m->saveUser($profile);
    }
  }

  private function textMessage($event){
    $userMessage = $event['message']['text'];
    if($userMessage !== 'Kamu Mengeluarkan Gunting' && $userMessage !== 'Kamu Mengeluarkan Kertas' && $userMessage !== 'Kamu Mengeluarkan Batu' && $userMessage !== 'Lihat Score')
      {

      if(strtolower($userMessage) == '1. mulai bermain')
      {
        // reset score
        $this->tebakkode_m->setScore($this->user['user_id'], 0);

        // send question no.1
        $this->sendChoice($event['replyToken']);
      } 
      
      elseif(strtolower($userMessage) == '2. panduan') {
        $code = array("wink" => "\u{10008F}", 
                    "Gunting" => "\u{100030}",
                    "Batu" => "\u{100031}",
                    "Kertas" => "\u{100032}");

      $opsi = ["1. Mulai Bermain","2. Panduan"];
      $length = count($opsi);

      for($i = 0; $i<$length; $i++){
        $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsi[$i]);
      } 

      // prepare button template
      $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
  
      // build message
      $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
      
      $message = "Kamu tinggal memilih salah satu diantara 3 pilihan:\n";
      $message .= "Gunting Kertas dan Batu.\n";
      $message .= "Setelah memilih, pilihan kamu akan di bandingkan oleh pilihan bot.\n";
      $message .= "Kamu akan mendaatkan score jika pilihan mu dapat mengalahkan pilihan bot.\n";
      
      $textMessageBuilder = new TextMessageBuilder($message);

      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder);
      $multiMessageBuilder->add($messageBuilder);
      
      // send reply message
      $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
      }
      else {
        $code = array("wink" => "\u{10008F}", 
                    "Gunting" => "\u{100030}",
                    "Batu" => "\u{100031}",
                    "Kertas" => "\u{100032}");

      $opsi = ["1. Mulai Bermain","2. Panduan"];
      $length = count($opsi);

      for($i = 0; $i<$length; $i++){
        $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsi[$i]);
      } 

      // prepare button template
      $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
  
      // build message
      $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
      
      $message = "Pilih Opsi 1. Mulai Bermain, untuk memulai permainan.\n";
      $message .= "pilih Opsi 2. Panduan, untuk mengetahui tata cara permainan.\n";
      $message .= "Selamat Bermain! " . $code["wink"];
      $textMessageBuilder = new TextMessageBuilder($message);

      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder);
      $multiMessageBuilder->add($messageBuilder);
      
      // send reply message
      $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
      }
    } else {
      $this->checkResult($userMessage,$event['replyToken']);
    }
  }

  private function stickerMessage($event){
    // create sticker message
    $stickerMessageBuilder = new StickerMessageBuilder(1, 106);

    $code = "\u{10008F}";

    // create text message
    $message = "Pilih Opsi 1. Mulai Bermain, untuk memulai permainan.\n";
    $message .= "pilih Opsi 2. Panduan, untuk mengetahui tata cara permainan.\n";
    $message .= "Selamat Bermain! " . $code;
    $textMessageBuilder = new TextMessageBuilder($message);

    // merge all message
    $multiMessageBuilder = new MultiMessageBuilder();
    $multiMessageBuilder->add($stickerMessageBuilder);
    $multiMessageBuilder->add($textMessageBuilder);

    // send message
    $this->bot->replyMessage($event['replyToken'], $multiMessageBuilder);
  }

  public function sendChoice($replyToken){

    $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
    $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
    $length = count($opsi);

    for($i = 0; $i<$length; $i++){
      $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
    } 

    // prepare button template
    $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
  
    // build message
    $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
    // prepare button template
 
    // send message
    $response = $this->bot->replyMessage($replyToken, $messageBuilder);
  }

  private function checkResult($playerChoice, $replyToken){

    $compareChoice = $this->tebakkode_m->compareChoice($message);

    switch($compareChoice){
      case "Bot Mengeluarkan Gunting, Seri":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score'] = $this->user['score'];
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Batu, Seri":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score'] = $this->user['score'];
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Kertas, Seri":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score'] = $this->user['score'];
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Gunting, Bot Menang":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Batu, Bot Menang":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Kertas, Bot Menang":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Gunting, Bot Kalah":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        
        $this->tebakkode_m->$comScore++;

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Batu, Bot Kalah":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->tebakkode_m->$comScore++;

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Bot Mengeluarkan Kertas, Bot Kalah":
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);

        $this->tebakkode_m->$comScore++;

        $this->bot->replyMessage($replyToken, $textMessageBuilder1);
        break;
      case "Lihat Score":
        // create user score message
        $message = 'Skormu '. $this->tebakkode_m->get_comScore() . ' Skor Komp' . $this->user['score'];
        $textMessageBuilder1 = new TextMessageBuilder($message);

        // merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
  
        // send reply message
        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
    }
    // //if answer is true, increment score
    // if($compareChoice == "Bot Mengeluarkan Batu, Bot Menang"){
    //   $message = "Bot Mengeluarkan Batu, Bot Menang";
    //   $textMessageBuilder1 = new TextMessageBuilder($message);

    //   $this->tebakkode_m->$comScore++;

    //   $this->bot->replyMessage($replyToken, $textMessageBuilder1);

    // }
    // elseif($compareChoice == "Bot Mengeluarkan Gunting, Seri"){
    //   $message = "Bot Mengeluarkan Gunting, Seri!";
    //   $textMessageBuilder1 = new TextMessageBuilder($message);

    //   $this->user['score'] = $this->user['score'];
    //   $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

    //   $this->bot->replyMessage($replyToken, $textMessageBuilder1);

    // }
    // elseif($compareChoice == "Bot Mengeluarkan Kertas Bot Kalah"){
    //   $message = "Bot Kalah!";
    //   $textMessageBuilder1 = new TextMessageBuilder($message);

    //   $this->user['score']++;
    //   $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);
    //   //$this->tebakkode_m->set_comScore($this->$CompScore);

    //   $this->bot->replyMessage($replyToken, $textMessageBuilder1);

    // }
    // else {
    //   // create user score message
    //   $message = 'Skormu '. $this->tebakkode_m->get_comScore() . ' Skor Komp' . $this->user['score'];
    //   $textMessageBuilder1 = new TextMessageBuilder($message);

    //   // merge all message
    //   $multiMessageBuilder = new MultiMessageBuilder();
    //   $multiMessageBuilder->add($textMessageBuilder1);

    //   // send reply message
    //   $this->bot->replyMessage($replyToken, $multiMessageBuilder);
    // }
  }

}
