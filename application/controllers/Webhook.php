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
        $this->tebakkode_m->setScoreComp($this->user['user_id'], 0);

        // send Choices
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
      $message .= "Kamu akan mendapatkan score jika pilihan mu dapat mengalahkan pilihan bot.\n\n";
      $message2 .= "P.S : Kamu tidak perlu membalas pesan dari bot secara manual, cukup memilih pilihan yang sudah tersedia.".$code["wink"];
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

    $compareChoice = $this->tebakkode_m->compareChoice($playerChoice);

    if($compareChoice) { 
    switch($compareChoice){
      case "Bot Mengeluarkan Gunting, Seri":

        $this->user['score'] = $this->user['score'];
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        
        break;
        case "Bot Mengeluarkan Batu, Seri":

          $this->user['score'] = $this->user['score'];
          $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

          $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
          $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
          $length = count($opsi);
  
          for($i = 0; $i<$length; $i++){
            $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
          } 
  
          // prepare button template
          $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
        
          // build message
          $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
          $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);
  
          //merge all message
          $multiMessageBuilder = new MultiMessageBuilder();
          $multiMessageBuilder->add($textMessageBuilder1);
          $multiMessageBuilder->add($messageBuilder);
  
          $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Kertas, Seri":

        $this->user['score'] = $this->user['score'];
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Gunting, Bot Menang":

        $this->user['scoreComp']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['scoreComp']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Batu, Bot Menang":

        $this->user['scoreComp']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['scoreComp']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Kertas, Bot Menang":

        $this->user['scoreComp']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['scoreComp']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Gunting, Bot Kalah":
        
        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Batu, Bot Kalah":

        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
      case "Bot Mengeluarkan Kertas, Bot Kalah":

        $this->user['score']++;
        $this->tebakkode_m->setScore($this->user['user_id'], $this->user['score']);

        $opsi = ["Gunting","Kertas","Batu","Lihat Score"];
        $opsiText = ["Kamu Mengeluarkan Gunting","Kamu Mengeluarkan Kertas","Kamu Mengeluarkan Batu","Lihat Score"];
        $length = count($opsi);

        for($i = 0; $i<$length; $i++){
          $options[] = new MessageTemplateActionBuilder($opsi[$i],$opsiText[$i]);
        } 

        // prepare button template
        $buttonTemplate = new ButtonTemplateBuilder(null, 'Janken-Bot Game!', null, $options);
      
        // build message
        $textMessageBuilder1 = new TextMessageBuilder($compareChoice);
        $messageBuilder = new TemplateMessageBuilder("Gunakan mobile app untuk melihat soal", $buttonTemplate);

        //merge all message
        $multiMessageBuilder = new MultiMessageBuilder();
        $multiMessageBuilder->add($textMessageBuilder1);
        $multiMessageBuilder->add($messageBuilder);

        $this->bot->replyMessage($replyToken, $multiMessageBuilder);
        break;
    }
  } 
    else {
      // create user score message
      $message = 'Skormu '. $this->user['score'] . ' Skor Bot ' . $this->tebakkode_m->get_comScore();
      $textMessageBuilder1 = new TextMessageBuilder($message);

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

      // merge all message
      $multiMessageBuilder = new MultiMessageBuilder();
      $multiMessageBuilder->add($textMessageBuilder1);
      $multiMessageBuilder->add($messageBuilder);

      // send reply message
      $this->bot->replyMessage($replyToken, $multiMessageBuilder);
    }
    
  }

}
