<?php
require_once(__DIR__ . '/vendor/autoload.php');
error_reporting(0);
use GuzzleHttp\Client;
function _get($client,$url) {
    return $client->get($url);
}
function new_client() {
    $client = new Client([
        'timeout' => 20, 
        'verify' => false,
    ]);
    return $client;
}
function get_html_from_url($url) {
    $client = new_client(); 
    try {
        $r = _get($client,$url);
        if ( $r->getStatusCode() != 200 ) {
            echo "Failed " . $r->getStatusCode() . ": " . $r->getReasonPhrase() . "\n";
            return false;
        } 
    } catch ( \Exception $e ) {
        echo "Error: " . $e->getMessage() . "\n";
        file_put_contents(__DIR__ . '/failed.log', print_r($e,true) . "\n", FILE_APPEND);
    }
    return $r->getBody();
}
function get_sku($body) {
    $dom = new DOMDocument();
    $dom->loadHTML($body);
    $xpath = new DOMXpath($dom);
    $exp = "//*[@id=\"descItemNumber\"]";
    $elements = $xpath->query($exp);
    foreach ( $elements as $el ) {
        $id = $el->textContent;
        return $id;
    }
    return false;
}
function handle_hip_stamp_sold($mb,$mail) {
    echo "_____" . __FUNCTION__ . "______\n";
    echo $mail->headers->subject . "\n";
    echo $mail->messageId . "\n";
    $body = $mb->getMail($mail->id,false);
    if ( $body->textHtml ) {
        $body = $body->textHtml;
    } else {
        $body = $body->textPlain;
    }
    preg_match('/\(#\d+\)\s*\(([\w\d]+)\)\s*\(\w:\d+\)/',$body,$match);
    if ( !empty($match) && !empty($match[1]) ) {
        $sku = $match[1];
        $notify = str_replace(['%sku','%ebay_id'],[$sku,''],getenv("ON_SOLD"));
        echo "URL: $notify\n";
        $body = trim(get_html_from_url($notify));
        if ( !$body ) {
            $body = 'NULL';
        }
        echo "BODY: $body\n";
        if ( $body == 'NOTFOUND' ) {
            file_put_contents(__DIR__ . "/failed.log", "SKU: $sku\n", FILE_APPEND);
        } 
        if ( $body != 'NULL' ) {
            $mb->moveMail($mail->id,'HANDLED');
            return true;
        }
    }
    $mb->moveMail($mail->id,'FAILED');
    echo "_____" . __FUNCTION__ . "______\n";
    return false;
}
function handle_offer_received($mb,$mail) {
    echo "_____" . __FUNCTION__ . "______\n";
    echo $mail->headers->subject . "\n";
    echo $mail->messageId . "\n";
    $body = $mb->getMail($mail->id,false);
    if ( $body->textHtml ) {
        $body = $body->textHtml;
    } else {
        $body = $body->textPlain;
    }
    preg_match('/Item ID: ([\d]+)/',$body,$match);
    if ( !empty($match) && !empty($match[1]) ) {
        $ebay_id = $match[1];
        echo "EBAYID: $ebay_id\n";
        $notify = str_replace(['%sku','%ebay_id'],['',$ebay_id],getenv("ON_SOLD"));
        echo "URL: $notify\n";
        $body = trim(get_html_from_url($notify));
        if ( !$body ) {
            $body = 'NULL';
        }
        echo "BODY: $body\n";
        if ( $body == 'NOTFOUND' ) {
            file_put_contents(__DIR__ . "/failed.log", "EBAYID: $ebay_id\n", FILE_APPEND);
        } 
        if ( $body != 'NULL' ) {
            $mb->moveMail($mail->id,'HANDLED');
            return true;
        }
    }
    $mb->moveMail($mail->id,'FAILED');
    echo "_____" . __FUNCTION__ . "______\n";
    return false;
}
function handle_sold_item($mb,$mail) {
    echo "_____" . __FUNCTION__ . "______\n";
    echo $mail->headers->subject . "\n";
    echo $mail->messageId . "\n";
    $body = $mb->getMail($mail->id,false);
    if ( $body->textHtml ) {
        $body = $body->textHtml;
    } else {
        $body = $body->textPlain;
    }
    preg_match('/\(([\d]+)\)$/',$mail->subject,$match);
    if ( ! empty($match) && !empty($match[1]) ) {
        $ebay_id = $match[1];
        echo "EBAYID: $ebay_id\n";
        $notify = str_replace(['%sku','%ebay_id'],['',$ebay_id],getenv("ON_SOLD"));
        echo "URL: $notify\n";
        $body = trim(get_html_from_url($notify));
        if ( !$body ) {
            $body = 'NULL';
        }
        echo "BODY: $body\n";
        if ( $body == 'NOTFOUND' ) {
            file_put_contents(__DIR__ . "/failed.log", "EBAYID: $ebay_id\n", FILE_APPEND);
        } 
        if ( $body != 'NULL' ) {
            $mb->moveMail($mail->id,'HANDLED');
            return true;
        }
    }
    $mb->moveMail($mail->id,'FAILED');
    echo "_____" . __FUNCTION__ . "______\n";
    return false;
}
$dotenv = Dotenv\Dotenv::create(__DIR__);
$dotenv->overload();
$mailbox = new PhpImap\Mailbox(
    getenv('IMAP_SERVER'),
    getenv('IMAP_UNAME'),
	getenv('IMAP_PWORD'), 
	getenv('DOWNLOADS') ? getenv('DOWNLOADS') : __DIR__,
	getenv('IMAP_ENCODING')
);
$mailbox->setAttachmentsIgnore(true);

try {
	// Get all emails (messages)
	// PHP.net imap_search criteria: http://php.net/manual/en/function.imap-search.php
	$mail_ids = $mailbox->searchMailbox('ALL');
} catch(PhpImap\Exceptions\ConnectionException $ex) {
	echo "IMAP connection failed: " . $ex;
	die();
}

if(!$mail_ids) {
	die('Mailbox is empty');
}
function hip_stamp_subjects($subject) {
    if ( preg_match('/Sale Notification/', $subject ) ) {
        return true;
    }
    if ( preg_match('/You Accepted an Offer on HipStamp Item:/', $subject ) ) {
        return true;
    }
    return false;
}
$date = date("Y-m-d H:i:s");
echo "Beginning: $date" . PHP_EOL;
foreach ( $mail_ids as $id ) {
    $mail = $mailbox->getMail($id);
    $subject = $mail->subject;
    if ( preg_match('/You\'ve sold your eBay item:/',$subject) ) {
        if ( handle_sold_item($mailbox,$mail) ) {
           echo "Handled: " . $mail->id . " \n"; 
        }
        continue;
    }
    if ( 
        preg_match('/New offer received:/', $subject) || 
        preg_match('/Counter-offer received:/',$subject) 
    ) {
        if ( handle_offer_received($mailbox,$mail) ) {
            echo "Handled: " . $mail->id . PHP_EOL;
        }
        continue;
    }
    if ( 
        hip_stamp_subjects($subject) && 
        preg_match('/hipstamp.com/',$mail->fromAddress) 
    ) {
        if ( handle_hip_stamp_sold($mailbox,$mail) ) {
            echo "Handled: " . $mail->id . PHP_EOL;
        } 
        continue;
    }
    echo "Don't know what to do with: {$subject}\n";
    $mailbox->moveMail($mail->id,'DISREGARD');
}
echo "Ending: $date" . PHP_EOL;
