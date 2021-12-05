<?php
/*
 * Copyright (c) 2020-2021, D9ping
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 * 
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 * 
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies,
 * either expressed or implied.
 */

/**
 * Class for creating a mail/RFC822 message.
 */
class BrachioMailer {

    const MIMEVERSION = '1.0';

    const MAILLINEMAXLENGTHHEADER = 998;

    // Sendmail relay support actually support up till 2040 characters per line.
    // http://www.jebriggs.com/blog/2010/07/smtp-maximum-line-lengths/
    // We try to limit it to 998 characters excluding enters(\r\n)
    //  as a MUST in RFC 5322.
    const MAILLINEMAXLENGTHBODY = 998;

    const CONVMAILMAXLINKS = 32;

    const BOUNDARYPREFIX = '--';

    const ENDPARTSUFFIX = '--';

    const DEFAULTHASHCASHCALCBITS = 20;

    private $attachments = array();

    private $autosubmitted = 'auto-generated';
    private $delay = 0;
    private $debugmode = false;
    private $messagecharset = 'UTF-8';
    private $messagecontenttype = 'text/plain';
    private $organization = null;
    private $replyto = null;
    private $returnpath = null;
    private $scheduleFor = false;
    private $precedencebulk = false;
    private $nopublicarchive = false;
    private $nondeliveryreport = false;
    private $importance = '';
    private $pgpkeyid = null;
    private $pgpkeyfingerprint = null;
    private $pgpkeygetkeyserverurl = null;
    private $pgpSignKeyFp = '';
    private $pgpEncryptPubKeyFp = '';
    private $sensitivity = null;
    private $abuseemail = null;
    private $abuseurl = null;
    private $includeipsender = true;
    private $useencodedip = false;
    private $usexpriority = false;
    private $eipencryptionkey = '';
    private $reportmailer = true;
    private $dispositionnotificationto = null;
    private $returnreceiptto = null;
    private $usehashcash = false;
    private $usesmime = false;
    private $smimecachefolder = null;
    private $smimekeypublic = null;
    private $smimekeyprivate = null;
    private $smimekeyprivatepassphrase = null;
    private $smimeextracerts = '';

    /**
     * Creating a new instance of BrachioMailer class.
     *
     * @param bool $debugmode In debug mode the generated mail message will be outputted to the
     *                        client as eml file instead of being send/scheduled.
     */
    public function __construct($debugmode = false)
    {
        $this->debugmode = (bool)$debugmode;
        if (!function_exists('quoted_printable_encode')) {
            throw new Exception('Please upgrade PHP to at least version 5.3.');
        }

        if (!defined('CHRENTER')) {
            define('CHRENTER', "\r\n");
        }
    }

    /**
     * RFC2045, RFC2046, RFC2047, RFC4288, RFC4289 and RFC2049 MIME content type.
     *
     * @param string $messageContentType The message content-type e.g. this can be: "text/plain" or "text/html"
     */
    public function setMessagecontenttype($messageContentType)
    {
        $this->messagecontenttype = $messageContentType;
    }

    /**
     * The character set of the data part of the message.
     *
     * @param string $messageCharset The character set of the content of the message. 
     *                               E.g. this can be: "7bit", "8bit", "UCS-4" etc.
     */
    public function setMessagecharset($messageCharset)
    {
        $validencoding = false;
        $listEncodings = mb_list_encodings();
        $numEncodings = count($listEncodings);
        if ($numEncodings > 10) {
            // Start from "7bit"
            for ($i = 11; $i < $numEncodings; ++$i) {
                if ($listEncodings[$i] === $messageCharset) {
                    $validencoding = true;
                    break;
                }
            }
        }

        if (!$validencoding) {
            throw new InvalidArgumentException('No valid message charset used.');
            return;
        }

        $this->messagecharset = $messageCharset;
    }

    /**
     * Unofficial. RFC 1036 2.2.8. The organization of the send message.
     *
     * @param string $organization The organization that sends the message.
     */
    public function setOrganization($organization)
    {
        $this->organization = $organization;
    }

    /**
     * The e-mail address to reply to. 
     * If this is set (not null or empty string) it will be used otherwise Reply-to: header is not included.
     *
     * @param string $replyTo The reply to e-mail address to send a reaction to this message to.
     */
    public function setReplyto($replyTo)
    {
        if (!filter_var($replyTo, FILTER_VALIDATE_EMAIL) ||
            strpos($replyTo, ' ') !== false ||
            strpos($replyTo, "\r") !== false ||
            strpos($replyTo, "\n") !== false) {
            throw new InvalidArgumentException(sprintf('The %1$s value is not a valid e-mail address.', '$replyTo'));
            return;
        }

        $this->replyto = $replyTo;
    }

    /**
     * The e-mail address to return the e-mail to in case of errors.
     * If not set the emailfrom parameter will be used as returnpath/envelope from email address.
     *
     * @param string $returnPath The bounce\envelope e-mail address.
     */
    public function setReturnpath($returnPathEmailaddr)
    {
        if (!filter_var($returnPathEmailaddr, FILTER_VALIDATE_EMAIL) ||
            strpos($returnPathEmailaddr, ' ') !== false ||
            strpos($returnPathEmailaddr, "\r") !== false ||
            strpos($returnPathEmailaddr, "\n") !== false) {
            throw new InvalidArgumentException(sprintf('The %1$s value is not a valid e-mail address.', '$returnPathEmailaddr'));
            return;
        }

        $this->returnpath = $returnPathEmailaddr;
    }

    /**
     * Unofficial. RFC2076, used to mark mass mailing send at once it's use is now unofficial and discouraged.
     * Warning: it's not recommeded to use this.
     *
     * @param bool $precedenceBulk True to use the Precedence: bulk mail header to mark mass mailing(discouraged).
     */
    public function setPrecedencebulk($precedenceBulk)
    {
        $this->precedencebulk = (bool)$precedenceBulk;
    }

    /**
     * Unofficial. Do not archive the message in publicly available archives.
     *
     * @param bool $noPublicArchive
     */
    public function setNopublicarchive($noPublicArchive)
    {
        $this->nopublicarchive = (bool)$noPublicArchive;
    }

    /**
     * Unofficial. Try preventing receiving Non delivery reports.
     * Warning: this header has often no effect at all.
     *
     * @param bool $nonDeliveryReport True to add the mail headers to prevent Non delivery reports.
     */
    public function setNondeliveryreport($nonDeliveryReport)
    {
        $this->nonondeliveryreport = (bool)$nonDeliveryReport;
    }

    /**
     * RFC2421 section 4.2.14: A hint from the sender how important a message is.
     *
     * @param string $importance Can be high, normal or low.
     */
    public function setImportance($importance)
    {
        if ($importance !== 'high' &&
            $importance !== 'normal' &&
            $importance !== 'low') {
            throw new InvalidArgumentException(sprintf('Invalid %1$s value.', '$importance'));
            return;
        }

        $this->importance = $importance;
    }

    /**
     * RFC2156: Autosubmitted header.
     * If you are sending a 'templated' standard message you should set it at: auto-generated.
     *
     * @param string $autoSubmitted Can be "not-auto-submitted", "auto-generated", "auto-replied" 
     *                              or "auto-forwarded".
     */
    public function setAutosubmitted($autoSubmitted)
    {
        $autoSubmittedLowercase = mb_strtolower($autoSubmitted);
        if ($autoSubmittedLowercase !== '' &&
            $autoSubmittedLowercase !== 'not-auto-submitted' &&
            $autoSubmittedLowercase !== 'auto-generated' &&
            $autoSubmittedLowercase !== 'auto-replied' &&
            $autoSubmittedLowercase !== 'auto-forwarded') {
            throw new InvalidArgumentException(sprintf('Invalid %1$s value.', '$autoSubmitted'));
            return;
        }

        $this->autosubmitted = $autoSubmittedLowercase;
    }

    /**
     * Unofficial. Use the X-Priority header.
     * warning the use of this header is not recommeded.
     *
     * @param bool $useXPriority True to use the X-Priority header to define the priority based on
     *                           a scale from 1(highest) to 5(lowest) of the message.
     */
    public function setUseXPriority($useXPriority)
    {
        $this->usexpriority = (bool)$useXPriority;
    }

    /**
     * Set the OpenPGP short KeyId.
     *
     * @param string $pgpKeyId The hexidecimal PGP key-Id.
     */
    public function setPgpkeyid($pgpKeyId)
    {
        if (strlen($pgpKeyId) < 8) {
            throw new InvalidArgumentException('PGP Key-Id too short.');
        }

        $this->pgpkeyid = $pgpKeyId;
    }

    /**
     * Set the OpenPGP fingerprint.
     *
     * @param string $pgpKeyFingerprint The hexidecimal PGP key fingerprint.
     */
    public function setPgpkeyfingerprint($pgpKeyFingerprint)
    {
        if (strlen($pgpKeyFingerprint) < 40) {
            throw new InvalidArgumentException('PGP fingerprint too short.');
        }

        $this->pgpkeyfingerprint = $pgpKeyFingerprint;
    }

    /**
     * Key server to get the OpenPGP public key.
     *
     * @param string $pgpKeyserverUrl A url to a server to get the PGP key from.
     */
    public function setPgpkeygetkeyserverurl($pgpKeyserverUrl)
    {
        if (!filter_var($pgpKeyserverUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException(sprintf('%1$s is not a valid URL.', '$pgpKeyserverUrl'));
            return;
        }

        $this->pgpkeygetkeyserverurl = $pgpKeyserverUrl;
    }

    /**
     * RFC2421 section 4.2.13 Indicates the requested privacy level.
     *
     * @param string $sensitivity The sensitivity level of the message.
     *                            This can be: "personal", "private" or "confidential".
     */
    public function setSensitivity($sensitivity)
    {
        if ($sensitivity !== '' &&
            $sensitivity !== 'personal' &&
            $sensitivity !== 'private' &&
            $sensitivity !== 'confidential') {
            throw new InvalidArgumentException(sprintf('Invalid %1$s value.', '$sensitivity'));
            return;
        }

        $this->sensitivity = $sensitivity;
    }

    /**
     * unofficial. E-mail address for reporting abuse for this message.
     *
     * @param string $abuseEmailAddr The e-mail address to report abuse on.
     */
    public function setAbuseemail($abuseEmailAddr)
    {
        if (!filter_var($abuseEmailAddr, FILTER_VALIDATE_EMAIL) ||
            strpos($abuseEmailAddr, ' ') !== false ||
            strpos($abuseEmailAddr, "\r") !== false ||
            strpos($abuseEmailAddr, "\n") !== false) {
            throw new InvalidArgumentException(sprintf('The %1$s value is not a valid e-mail address.',
                                                       $abuseEmailAddr));
            return;
        }

        $this->abuseemail = $abuseEmailAddr;
    }

    /**
     * unofficial. Information url for reporting abuse for this message.
     *
     * @param string $abuseUrl A url to report abuse on.
     */
    public function setAbuseurl($abuseUrl)
    {
        if (!filter_var($abuseUrl, FILTER_VALIDATE_URL)) {
            if (!empty($abuseUrl)) {
                throw new InvalidArgumentException(sprintf('%1$s is not a valid URL.', $abuseUrl));
            } else {
                throw new InvalidArgumentException(sprintf('No valid URL provided.', $abuseUrl));
            }
        }

        $this->abuseurl = $abuseUrl;
    }

    /**
     * unofficial. Should the ip address of the client/user be included in the message.
     *
     * @param bool $includeIpSender True to include the ip address of the submitter of the message, 
     *                              it will be encrypted if setUseencodedip is set to true.
     */
    public function setIncludeipsender($includeIpSender)
    {
        $this->includeipsender = (bool)$includeIpSender;
    }

    /**
     * unofficial. Include an encrypted the ip address of the requested sender in the header.
     * warning: mcrypt php extension needs to be loaded to use it.
     * 
     * @param bool $useEncodedIp If true the X-EIP header will be added and the X-Originating-IP will not be used.
     */
    public function setUseencodedip($useEncodedIp)
    {
        if (!extension_loaded('mcrypt') && !function_exists('openssl_encrypt') && $useEncodedIp) {
            error_log('mcrypt php extension not loaded and openssl_encrypt also not available, privacy risk because encrypted X-EIP not used.');
        } else {
            $this->useencodedip = (bool)$useEncodedIp;
        }
    }

    /**
     * The secret encryption key for encrypting the ip address and the ciphertext is included in the
     * X-EIP header of the message. It's recommended to use a key that is at least 16 characters
     * or longer.
     *
     * @param string $eipEncryptionKey The secret encryption key used for generating the X-EIP header.
     */
    public function setEipencryptionkey($eipEncryptionKey)
    {
        $this->eipencryptionkey = $eipEncryptionKey;
    }

    /**
     * unofficial. Report the client software used. This is PHP/Major.Minor version numbers.
     *
     * @param bool $reportMailer True to report the version of PHP used.
     */
    public function setReportmailer($reportMailer)
    {
        $this->reportmailer = (bool)$reportMailer;
    }

    /**
     * Request for the receiving mailclient/MUA to send a Delivery Status Notification/DSN message 
     * as soon as the person opens the email. Warning a lot of mailclients/MUA's will warn the
     * user before sending a Delivery Status Notification(DSN) message. It's also possible
     * that always a denied disposition message is send.
     *
     * @param string $dispositionNotificationEmail The e-mail address that receives the DSN.
     */
    public function setDispositionnotificationto($dispositionNotificationEmail)
    {
        if (!filter_var($dispositionNotificationEmail, FILTER_VALIDATE_EMAIL) ||
            strpos($dispositionNotificationEmail, ' ') !== false ||
            strpos($dispositionNotificationEmail, "\r") !== false ||
            strpos($dispositionNotificationEmail, "\n") !== false) {
            throw new InvalidArgumentException(sprintf('The %1$s value is not a valid e-mail address.', $dispositionNotificationEmail));
            return;
        }

        $this->dispositionnotificationto = $dispositionNotificationEmail;
    }

    /**
     * Request for the receiving mail server/MTA to send a DSN (delivery status notification) as soon as it receives the email.
     *
     * @param string $returnReceiptToEmail The email address that receives the DSN.
     */
    public function setReturnreceiptto($returnReceiptToEmail)
    {
        if (!filter_var($returnReceiptToEmail, FILTER_VALIDATE_EMAIL) ||
            strpos($returnReceiptToEmail, ' ') !== false ||
            strpos($returnReceiptToEmail, "\r") !== false ||
            strpos($returnReceiptToEmail, "\n") !== false) {
            throw new InvalidArgumentException(sprintf('The %1$s value is not a valid e-mail address.', $returnReceiptToEmail));
            return;
        }

        $this->returnreceiptto = $returnReceiptToEmail;
    }

    /**
     * Delay in milliseconds before try sending the mail to the MTA.
     *
     * @param int $delayMilliseconds A positive number of milliseconds to delay the handing off the email to sendmail.
     */
    public function setDelay($delayMilliseconds)
    {
        if ($delayMilliseconds < 0) {
            throw new InvalidArgumentException(sprintf('Invalid %1$s value.', '$delayMiliseconds'));
        }

        $this->delay = (int)$delayMilliseconds;
    }

    /**
     * Use the X-Hashcash proof of work anti-spam header.
     * Enabling hashcash will make send a mails very slow as designed.
     *
     * @param bool $useHashCash True to generate the HashCash header. Requires a lot of CPU use 
     *                          and the Hashcash class.
     */
    public function setUsehashcash($useHashCash)
    {
        if ($useHashCash) {
            require_once(__DIR__ .'/Hashcash.php');
        }

        $this->usehashcash = (bool)$useHashCash;
    }

    /**
     * Use the a database table to store scheduled mails to send at a later time.
     *
     * @param string $dateTime
     */
    public function ScheduleMailFor($dateTime)
    {
        if (empty($dateTime)) {
            throw new InvalidArgumentException('Required date and time missing.');
        }

        if (file_exists(__DIR__ .'/config.php')) {
            require_once(__DIR__ .'/config.php');
        }
        if (file_exists(__DIR__ .'/DB.php')) {
            require_once(__DIR__ .'/DB.php');
        }

        if (is_readable(__DIR__ .'/d_Mailschedule.php')) {
            require_once(__DIR__ .'/d_Mailschedule.php');
            $this->scheduleFor = $dateTime;
        } else {
            exit('d_Mailschedule class not readable or missing.');
        }
    }

    /**
     * Use S/MIME to (only)sign the message. 
     * This requires a S/MIME certificate signed by a trusted CA and the private key of the certificate.
     *
     * @param bool $useSmimeSigning True to sign the message with S/MIME.
     */
    public function setUsesmime($useSmimeSigning)
    {
        $this->usesmime = (bool)$useSmimeSigning;
    }

    /**
     * The S/MIME cache folder to store the signed and unsigned messages.
     *
     * @param string $smimeCacheFolder A path to a not public folder.
     */
    public function setSmimecachefolder($smimeCacheFolder)
    {
        if (!is_dir($smimeCacheFolder)) {
            throw new Exception(sprintf('%1$s value is not a folder.', '$smimeCacheFolder'));
        }

        $this->smimecachefolder = $smimeCacheFolder;
    }

    /**
     * Set the S/MIME public certificate.
     *
     * @param string $smimePublicKey The file path to the public certificate.
     */
    public function setSmimekeypublic($smimePublicKey)
    {
        if (!is_file($smimePublicKey)) {
            throw new Exception(sprintf('The %1$s value is not valid file path.', $smimePublicKey));
        }

        $this->smimekeypublic = $smimePublicKey;
    }

    /**
     * Set the S/MIME private key for the S/MIME certificate.
     *
     * @param string $smimePrivateKey The file path to the (possible encypted)certificate.
     */
    public function setSmimekeyprivate($smimePrivateKey)
    {
        if (!is_file($smimePrivateKey)) {
            throw new Exception(sprintf('The %1$s value is not a file.', '$smimePrivateKey'));
        }

        $this->smimekeyprivate = $smimePrivateKey;
    }

    /**
     * Set the passphrase for the private key for the S/MIME certificate.
     *
     * @param string $smimekeyprivatepassphrase The secret passphrase to decrypt the private certificate.
     */
    public function setSmimekeyprivatepassphrase($smimekeyprivatepassphrase)
    {
        $this->smimekeyprivatepassphrase = $smimekeyprivatepassphrase;
    }

    /**
     * Set the intermediate CA Certificates.
     */
    public function setSmimeextracerts($smimeExtraCerts)
    {
        if (!is_file($smimeExtraCerts)) {
            throw new Exception(sprintf('The %1$s value is not a file.', '$smimeExtraCerts'));
        }

        $this->smimeextracerts = $smimeExtraCerts;
    }

    /**
     * Override the magic __debugInfo method (new in PHP 5.6.0) because
     * if the method isn't defined on an object, then ALL public, protected and private properties could be shown.
     */
    public function __debugInfo()
    {
        return array('error' => '__debugInfo disabled.');
    }

    /**
     * Override the magic __toString method.
     */
    public function __toString()
    {
        return '__toString disabled.';
    }

    /**
     * Sends an e-mail
     *
     * @param string $emailto   An valid e-mail address (required).
     * @param string $emailfrom An valid e-mail address (required).
     * @param string $subject   Subject of the email (required).
     * @param string $body      The message body of the email, by default text/plain MIME. (required).
     * @param string $nameto    The full name of the receiver. (optional)
     * @param string $namefrom  The full name of the sender. (optional)
     * @return bool True if mail is send or added in queue for sending later succesfully.
     */
    public function Send($emailto, $emailfrom, $subject, $body, $nameto = '', $namefrom = '')
    {
        if (empty($emailto)) {
            return false;
        }

        if (empty($emailfrom)) {
            return false;
        }

        if (empty($subject)) {
            return false;
        }

        // For the message to be stored in the database for scheduling the email
        // the to and from address and the subject is limited to 255 characters.
        // Excluding the header name e.g. 'Subject: '/'From:'  so we limit these
        //  provided fields to 200 characters.
        if (strlen($emailto) > 200) {
            error_log('To may not be more than 200 characters.');
            return false;
        }

        if (strlen($emailfrom) > 200) {
            error_log('From may not be more than 200 characters.');
            return false;
        }

        if (strlen($subject) > 200) {
            error_log('Subject may not be more than 200 characters.');
            return false;
        }

        if (empty($body)) {
            return false;
        }

        if (!filter_var($emailfrom, FILTER_VALIDATE_EMAIL) ||
            strpos($emailfrom, ' ') !== false ||
            strpos($emailfrom, "\r") !== false ||
            strpos($emailfrom, "\n") !== false) {
            return false;
        }

        if (empty($this->returnpath)) {
            $this->returnpath = $emailfrom;
        }

        if (empty($this->messagecharset)) {
            $this->messagecharset = 'UTF-8';
        }

        $headers = '';
        $this->addHeaderLine('Return-Path', $this->returnpath, $headers);
        if (empty($namefrom) || !$this->isValidName($namefrom)) {
            $this->addHeaderLine('From', $emailfrom, $headers);
        } else {
            if (preg_match("/^[a-zA-Z0-9\s\.\-\'\\\\,\/]+$/", $namefrom)) {
                // Only allow: a-z, A-Z, 0-9, space, dot, comma, dash, single quote, slash and backslash.
                $this->addHeaderLine('From', $namefrom.' <'.$emailfrom.'>', $headers);
            } else {
                mb_internal_encoding('UTF-8');
                $encFromName = mb_encode_mimeheader($namefrom, 'UTF-8', 'Q').' <'.$emailfrom.'>';
                $this->addHeaderLine('From', $encFromName, $headers);
            }
        }

        if (!empty($this->replyto)) {
            $this->addHeaderLine('Reply-To', $this->replyto, $headers);
        }

        if (!empty($this->dispositionnotificationto)) {
            $this->addHeaderLine('Disposition-Notification-To', $this->dispositionnotificationto, $headers);
        }

        if (!empty($this->returnreceiptto)) {
            $this->addHeaderLine('Return-Receipt-To', $this->returnreceiptto, $headers);
        }

        if (!empty(self::MIMEVERSION) && ctype_digit((string) self::MIMEVERSION)) {
            $headers .= 'MIME-Version: '.self::MIMEVERSION.CHRENTER;
        }

        if (!empty($this->autosubmitted)) {
            $this->addHeaderLine('Auto-Submitted', $this->autosubmitted, $headers);
        }

        if ($this->precedencebulk) {
            $this->addHeaderLine('Precedence', 'bulk', $headers);
        }

        if ($this->nondeliveryreport) {
            $this->addHeaderLine('Prevent-NonDelivery-Report', 'true', $headers);
        }

        if (!empty($this->pgpkeygetkeyserverurl) && !empty($this->pgpkeyid) && !empty($this->pgpkeyfingerprint)) {
            $this->addHeaderLine('OpenPGP', 'url="'.$this->pgpkeygetkeyserverurl.'"; id='.$this->pgpkeyid.';', $headers);
            $this->addHeaderLine('X-PGP-Key', 'fp="'.$this->pgpkeyfingerprint.'"; id="'.$this->pgpkeyid.'"; get=<'.$this->pgpkeygetkeyserverurl.'>;', $headers);
        } elseif (!empty($this->pgpkeygetkeyserverurl) && !empty($this->pgpkeyid)) {
            $this->addHeaderLine('OpenPGP', 'url='.$this->pgpkeygetkeyserverurl.'; id='.$this->pgpkeyid.';', $headers);
            $this->addHeaderLine('X-PGP-Key', 'fp="'.$this->pgpkeyfingerprint.'"; id="'.$this->pgpkeyid.'";', $headers);
        } elseif (!empty($this->pgpkeygetkeyserverurl) && empty($this->pgpkeyid)) {
            $this->addHeaderLine('OpenPGP', 'url='.$this->pgpkeygetkeyserverurl.';', $headers);
        } elseif (empty($this->pgpkeygetkeyserverurl) && !empty($this->pgpkeyid) && !empty($this->pgpkeyfingerprint)) {
            $this->addHeaderLine('OpenPGP', 'id='.$this->pgpkeyid.';', $headers);
            $this->addHeaderLine('X-PGP-Key', 'fp="'.$this->pgpkeyfingerprint.'"; id="'.$this->pgpkeyid.'";', $headers);
        } elseif (empty($this->pgpkeygetkeyserverurl) && !empty($this->pgpkeyid)) {
            $this->addHeaderLine('OpenPGP', 'id='.$this->pgpkeyid.';', $headers);
        }

        if (!empty($this->sensitivity)) {
            $this->addHeaderLine('Sensitivity', $this->sensitivity, $headers);
        }

        if (!empty($this->organization)) {
            $this->addHeaderLine('Organization', $this->organization, $headers, 255);
        }

        if (!empty($this->reportmailer)) {
            // We don't report the release version number or the extra section in the full php version string.
            $this->addHeaderLine('X-Mailer', 'PHP/'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION, $headers);
        }

        if ($this->nopublicarchive) {
            $this->addHeaderLine('X-No-Archive', 'Yes', $headers);
        }

        if (!empty($this->importance)) {
            $this->addHeaderLine('Importance', $this->importance, $headers);
            if ($this->usexpriority) {
                // Set the unofficial x-priority based on the set importance headers.
                // Possible X-Priority values are: 1 for highest, 2 for high/above-normal, 3 for normal, 4 for low/below-normal or 5 for lowest.
                $xpriority = 3;
                switch ($this->importance) {
                    case 'high':
                        $xpriority = 1;
                        break;
                    case 'low':
                        $xpriority = 5;
                        break;
                }

                $this->addHeaderLine('X-Priority', $xpriority, $headers);
            }
        }

        if (!empty($this->abuseurl)) {
            if (filter_var($this->abuseurl, FILTER_VALIDATE_URL)) {
                $this->addHeaderLine('X-Abuse-Info', $this->abuseurl, $headers);
            }
        }

        if (!empty($this->abuseemail)) {
            if (filter_var($this->abuseemail, FILTER_VALIDATE_EMAIL) &&
                strpos($this->abuseemail, ' ') === false &&
                strpos($this->abuseemail, "\r") === false &&
                strpos($this->abuseemail, "\n") === false) {
                $this->addHeaderLine('X-Report-Abuse-To', $this->abuseemail, $headers, 255);
                //$this->addHeaderLine('Abuse-Reports-To', $this->abuseemail, $headers, 255);
                //$this->addHeaderLine('X-Notice', $this->abuseemail, $headers, 255);
            }
        }

        if ($this->includeipsender) {
            $ipaddr = '';
            if (isset($_SERVER['REMOTE_ADDR'])) {
                $ipaddr = $_SERVER['REMOTE_ADDR'];
            }

            if (filter_var($ipaddr, FILTER_VALIDATE_IP)) {
                if ($this->useencodedip && !empty($this->eipencryptionkey) && 
                    (extension_loaded('mcrypt') || function_exists('openssl_encrypt')) ) {
                        $encodedip = '';
                        if (function_exists('openssl_encrypt')) {
                            // use openssl
                            $openssl_cipher_str = 'aes-128-cfb';  // cipher should be in lowercase.
                            if (!in_array($openssl_cipher_str, openssl_get_cipher_methods())) {
                                error_log('Error '.$openssl_cipher_str.' not supported by openssl!');
                            }

                            $sizeiv = openssl_cipher_iv_length($openssl_cipher_str);
                            $strongiv = true;
                            $iv = openssl_random_pseudo_bytes($sizeiv, $strongiv);
                            if (!$strongiv) {
                                usleep(100000);  // Try again in 100 ms.
                                $iv = openssl_random_pseudo_bytes($this->ivsize, $strongiv);
                                if (!$strongiv) {
                                    error_log('IV could not be generated on strong random data.');
                                }
                            }

                            $rawencodedip = openssl_encrypt($ipaddr, $openssl_cipher_str, $this->eipencryptionkey, OPENSSL_ZERO_PADDING, $iv);
                            $encodedip = base64_encode($iv).'/'.base64_encode($rawencodedip);
                        } else {
                            // use mcrypt
                            $cipher = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_CFB, '');
                            // Using cipher feedback(CFB) mode, best mode for encrypting byte streams where single bytes must be encrypted.
                            $sizeiv = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CFB);
                            // Use MCRYPT_DEV_RANDOM the blocking slow randomness generator.
                            $iv = mcrypt_create_iv($sizeiv, MCRYPT_DEV_RANDOM);
                            mcrypt_generic_init($cipher, $this->eipencryptionkey, $iv);
                            $rawencodedip = mcrypt_generic($cipher, $ipaddr);
                            $encodedip = base64_encode($iv).'/'.base64_encode($rawencodedip);
                            mcrypt_generic_deinit($cipher);
                            mcrypt_module_close($cipher);
                        }

                        // Include encrypted ip address in header.
                        $this->addHeaderLine('X-EIP', $encodedip, $headers, self::MAILLINEMAXLENGTHHEADER);
                } else {
                    // Set unencrypted ip address in header.
                    // The maximum length of a hexdec IPv6 address with IPv4 tunneling feature, is 45 characters.
                    $this->addHeaderLine('X-Originating-IP', '['.$ipaddr.']', $headers, 65); // 'X-Originating-IP: []'=> 20 characters
                }
            }
        }

        $orgmessage = $body;
        $body = '';
        $numAttachments = count($this->attachments);
        if ($numAttachments >= 1) {
            // Has attachments
            //$this->addHeaderLine('X-MS-Has-Attach', 'Yes', $headers);
            $multipartmixed = $this->GenerateBoundary('');
            if ($this->usesmime) {
                // S/mime signing will add "Content-Type: multipart/signed" to headers already
                // so Content-Type needs to be added to mail body instead.
                $body .= 'Content-Type: multipart/mixed;'.CHRENTER;
                $body .= "\t".'boundary="'.$multipartmixed.'"'.CHRENTER; // line folding
                $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                $body .= CHRENTER;
            } else {
                $headers .= 'Content-Type: multipart/mixed;'.CHRENTER;
                $headers .= "\t".'boundary="'.$multipartmixed.'"'.CHRENTER; // line folding
            }

            $body .= self::BOUNDARYPREFIX.$multipartmixed.CHRENTER;
            if ($this->messagecontenttype === 'text/html') {
                $multipartalternative = $this->GenerateBoundary($multipartmixed);
                $body .= 'Content-Type: multipart/alternative;'.CHRENTER;
                $body .= "\t".'boundary="'.$multipartalternative.'"'.CHRENTER; // line folding
                $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                $body .= CHRENTER;
                $body .= self::BOUNDARYPREFIX.$multipartalternative.CHRENTER;
            }

            // add plaintext content part
            $this->addHeaderLine('Content-Type', 'text/plain; charset='.$this->messagecharset, $body, self::MAILLINEMAXLENGTHBODY);
            $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
            $body .= CHRENTER;
            if ($this->messagecontenttype === 'text/html') {
                // add text/plain fallback from text/html part
                $body .= quoted_printable_encode($this->ConvertHtmlToText($orgmessage)).CHRENTER;

                // add text/html part
                $body .= self::BOUNDARYPREFIX.$multipartalternative.CHRENTER;
                $this->addHeaderLine('Content-Type', $this->messagecontenttype.'; charset='.$this->messagecharset, $body, self::MAILLINEMAXLENGTHBODY);
                $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                $body .= CHRENTER;
                $body .= quoted_printable_encode($orgmessage).CHRENTER;
            } else {
                // add text/plain part
                $body .= quoted_printable_encode($orgmessage).CHRENTER;
            }

            $body .= CHRENTER;

            // Add attachments
            $attachments = $this->attachments;
            foreach ($attachments as $attachmentname => $attachment) {
                $binaryFileContent = file_get_contents($attachment['file']);
                if ($binaryFileContent === false) {
                    error_log(sprint('Could not read %s.', $attachment['file']));
                    continue;
                }

                $body .= self::BOUNDARYPREFIX.$multipartmixed.CHRENTER;
                $encodedAttachmentname = $attachmentname;
                if (!preg_match("/^[a-zA-Z0-9\s\.\-\'\\\\,\/]+$/", $attachmentname)) {
                    $encodedAttachmentname = mb_encode_mimeheader($attachmentname, 'UTF-8', 'Q');
                }

                $this->addHeaderLine('Content-Type',
                                     $attachment['mime'].'; name="'.$encodedAttachmentname.'"',
                                     $body,
                                     self::MAILLINEMAXLENGTHBODY);

                // make file description shorter.
                $headerKeyMime = 'Content-Type';
                if (strlen($attachment['description']) > 253 - strlen($headerKeyMime)) {
                    $this->addHeaderLine($headerKeyMime, substr($attachment['description'], 0, 253 - strlen($headerKeyMime)), $body, self::MAILLINEMAXLENGTHBODY);
                } else {
                    $this->addHeaderLine('Content-Description', $attachment['description'], $body, self::MAILLINEMAXLENGTHBODY);
                }

                $body .= 'Content-Transfer-Encoding: base64'.CHRENTER;
                $body .= 'Content-Disposition: attachment;'.CHRENTER;
                $body .= "\t".'filename="'.$encodedAttachmentname.'"; size='.$attachment['size'].';'.CHRENTER;  // line folding
                $body .= CHRENTER;
                $body .= chunk_split(base64_encode($binaryFileContent)); 
            }

            $body .= self::BOUNDARYPREFIX.$multipartmixed.self::ENDPARTSUFFIX.CHRENTER;
        } else {
            // No attachments.
            //$this->addHeaderLine('X-MS-Has-Attach', 'No', $headers);
            $multipartalternative = '';
            if ($this->messagecontenttype === 'text/html') {
                $multipartalternative = $this->GenerateBoundary('');
            }

            if ($this->usesmime) {
                // S/mime signing will add "Content-Type: multipart/signed" to headers already
                // so Content-Type needs to be added to mail body instead.
                if ($this->messagecontenttype === 'text/html') {
                    // Create fallback part so use multipart/alternative.
                    //$body .= CHRENTER;
                    //$body .= self::BOUNDARYPREFIX.$multipartalternative.CHRENTER;
                    $body .= 'Content-Type: multipart/alternative;'.CHRENTER;
                    $body .= "\t".'boundary="'.$multipartalternative.'"'.CHRENTER; // line folding
                    $body .= CHRENTER;
                } else {
                    $this->addHeaderLine('Content-Type', $this->messagecontenttype.'; charset='.$this->messagecharset, $body, self::MAILLINEMAXLENGTHHEADER);
                    $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                    $body .= CHRENTER;
                }
            } else {
                if ($this->messagecontenttype === 'text/html') {
                    // Create fallback so use multipart/alternative in header.
                    $headers .= 'Content-Type: multipart/alternative;'.CHRENTER;
                    $headers .= "\t".'boundary="'.$multipartalternative.'"'.CHRENTER; // line folding
                } else {
                    $this->addHeaderLine('Content-Type', $this->messagecontenttype.'; charset='.$this->messagecharset, $headers, self::MAILLINEMAXLENGTHHEADER);
                }
            }

            if ($this->messagecontenttype === 'text/html') {
                // add text/plain fallback
                $body .= self::BOUNDARYPREFIX.$multipartalternative.CHRENTER;
                $this->addHeaderLine('Content-Type', 'text/plain; charset='.$this->messagecharset, $body, self::MAILLINEMAXLENGTHBODY);
                $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                $body .= CHRENTER;
                $body .= quoted_printable_encode($this->ConvertHtmlToText($orgmessage)).CHRENTER;

                // add text/html part
                $body .= self::BOUNDARYPREFIX.$multipartalternative.CHRENTER;
                $this->addHeaderLine('Content-Type', $this->messagecontenttype.'; charset='.$this->messagecharset, $body, self::MAILLINEMAXLENGTHBODY);
                $body .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
                $body .= CHRENTER;
            }

            $body .= quoted_printable_encode($orgmessage).CHRENTER;
        }

        $headers .= 'Content-Transfer-Encoding: quoted-printable'.CHRENTER;
        if (!empty($this->returnpath)) {
            if (strpos($this->returnpath, ':') !== false ||
                strpos($this->returnpath, "\r") !== false ||
                strpos($this->returnpath, "\n") !== false) {
                return;
            }

            // The user that the webserver runs as should be added as a trusted user to the sendmail
            // configuration to prevent a 'X-Warning' header from being added to the message when 
            // the envelope sender (-f) is set using mail.
            $arguments = '-f '.escapeshellarg($this->returnpath).' ';
        }

        if ($this->usehashcash) {
            $hashcash = new Hashcash(self::DEFAULTHASHCASHCALCBITS, $emailto);
            try {
                $this->addHeaderLine('X-Hashcash', $hashcash->mint(), $headers);
            } catch (Exception $hashcashExc) {
                error_log('Generation hashcash header error: '.$hashcashExc->getMessage());
            }
        }

        if ($this->usesmime) {
            $dtRndPrefix = date('Y-m-d').'_'.mt_rand(1,PHP_INT_MAX);
            $filenameMsgUnsigned = $dtRndPrefix.'_msg_unsigned.eml';
            $filenameMsgSigned = $dtRndPrefix.'_msg_signed.eml';

            $fpWriteMsgUnsigned = fopen($this->smimecachefolder.$filenameMsgUnsigned, 'w');
            fwrite($fpWriteMsgUnsigned, $body);
            fclose($fpWriteMsgUnsigned);

            define('FILEPROTOHANDLER', 'file://');
            if (!openssl_pkcs7_sign(
                    $this->smimecachefolder.$filenameMsgUnsigned,
                    $this->smimecachefolder.$filenameMsgSigned,
                    FILEPROTOHANDLER.$this->smimekeypublic,
                    array(FILEPROTOHANDLER.$this->smimekeyprivate, $this->smimekeyprivatepassphrase),
                    array(),
                    PKCS7_DETACHED,
                    $this->smimeextracerts)) {
                error_log('Error Could not smime sign email.');
                return false;
            }

            if ($fpMsgSigned = fopen($this->smimecachefolder.$filenameMsgSigned, 'c+')) {
                if (!flock($fpMsgSigned, LOCK_EX)) {
                    fclose($fpMsgSigned);
                }

                $body = '';
                $linenr = 0;
                $offset = 0;
                $lenSignedFile = $this->realFileSize($this->smimecachefolder.$filenameMsgSigned);
                // Rewrite signed message to exclude email header
                while (($line = fgets($fpMsgSigned, self::MAILLINEMAXLENGTHBODY)) !== false) {
                    $linenr++;
                    $lenline = strlen($line);
                    if ($linenr < 3) {
                        $offset += $lenline;
                        // Added the first 2 lines to $headers and remove them from $body.
                        $headers .= $line;
                        continue;
                    }

                    if ($linenr === PHP_INT_MAX) {
                        error_log('Exceeded maximum number of lines. abort.');
                        break;
                    }

                    // ftell may return unexpected results for files which are larger than 2GB. 
                    $pos = ftell($fpMsgSigned);
                    //error_log('linenr='.$linenr.',lenline='.$lenline.',pos='.$pos);
                    fseek($fpMsgSigned, $pos - $lenline - $offset);
                    fputs($fpMsgSigned, $line);
                    fseek($fpMsgSigned, $pos);
                    $body .= $line;
                }

                fflush($fpMsgSigned);
                if (bccomp($lenSignedFile, PHP_INT_MAX) !== 1) {
                    ftruncate($fpMsgSigned, ($lenSignedFile - $offset));
                }

                flock($fpMsgSigned, LOCK_UN);
                fclose($fpMsgSigned);
            }
        }

        if ($this->delay > 0) {
            // Deliberately delay sending the e-mail.
            usleep($this->delay);
        }

        if ($this->scheduleFor !== false) {
            // Schedule the mail to be send with sendmail.
            $mysqlScheduleDtStr = $this->scheduleFor->format('Y-m-d H:i:s');
            return d_Mailschedule::getInstance()->add($mysqlScheduleDtStr,
                                                      $emailto,
                                                      $subject,
                                                      $arguments,
                                                      $headers,
                                                      $body);
        }

        if ($this->debugmode) {
            header('X-Robots-Tag: noindex');
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: message/rfc822');
            header('Content-Disposition: attachment; filename="maildebug.eml"');
            $mail = $headers;
            $this->addHeaderLine('To', $emailto, $mail);
            $this->addHeaderLine('Subject', $subject, $mail);
            $mail .= CHRENTER;
            $mail .= $body;
            echo $mail;
            return true;
        }

        // Directly send the mail with sendmail.
        // It is worth noting that the mail() function is not suitable for larger volumes of email
        // in a loop. Mail opens and closes an SMTP socket for each email, which is not very
        // efficient. 
        return mail($emailto, $subject, $body, $headers, $arguments);
    }

    /**
     * Progress the scheduled mail database table to see if mail can be send and removed
     *  from the schedule mail queue.
     * Uses sendmail which opens and closes an SMTP socket for each email.
     *
     * @param $limit Maximum number of message to process now.
     */
    public function ProcressSchedule($limit = 4)
    {
        if (file_exists(__DIR__ .'/config.php')) {
            require_once(__DIR__ .'/config.php');
        }

        if (file_exists(__DIR__ .'/DB.php')) {
            require_once(__DIR__ .'/DB.php');
        }

        require_once(__DIR__ .'/d_Mailschedule.php');
        $emails = d_Mailschedule::getInstance()->GetAll($limit);
        $dtNow = new DateTime();
        foreach ($emails as &$email) {
            $dtScheduleFor = new DateTime($email['sendafter']);
            if ($dtScheduleFor < $dtNow) {
                if ($this->debugmode) {
                    error_log('DEBUG: ProcressSchedule, mail to:'.$email['to']);
                } else {
                    if (!mail($email['to'],
                              $email['subject'],
                              $email['body'],
                              $email['headers'],
                              $email['arguments'])) {
                        error_log('Sendmail did not accept the mail.');
                    }

                    if (!d_Mailschedule::getInstance()->remove($email['mailscheduleid'])) {
                        error_log('Could not remove mailscheduleid:'.$email['mailscheduleid']);
                        return;
                    }
                }
            }
        }
    }

    /**
    * Return file size
    * For file size over PHP_INT_MAX (2147483647 bytes / 2 Gb), PHP filesize function loops from
    *  -PHP_INT_MAX to PHP_INT_MAX to workaround the 32bit signed integer limit on 32bits php.
    *
    * @param string $path Path of the file
    * @return mixed File size(as string) or false if error
    */
    private function realFileSize($path)
    {
        $size = filesize($path);
        if (!($file = fopen($path, 'rb'))) {
            return false;
        }

        if ($size >= 0) {
            if (fseek($file, 0, SEEK_END) === 0) {
                fclose($file);
                return $size;
            }
        }

        // Quickly jump the first 2 GB with fseek. After that fseek is not working on 32 bit php (it uses int internally)
        $size = PHP_INT_MAX - 1;
        if (fseek($file, PHP_INT_MAX - 1) !== 0) {
            fclose($file);
            return false;
        }

        $length = 1024 * 1024;
        // Read in chunks of 1MiB
        while (!feof($file)) {
            $read = fread($file, $length);
            $size = bcadd($size, $length);
        }

        $size = bcsub($size, $length);
        $size = bcadd($size, strlen($read));
        fclose($file);
        return $size;
    }

    /**
     * Add a attachment to a e-mail.
     *
     * @param string $file           The file path to the file to include in the message.
     * @param string $attachmentname The filename of the attachment in the message. This can be different than $file.
     * @param string $mimetype       The MIME type of the attachment e.g. application/pdf or image/png etc.
     * @param string $description    The description text of the attachment. Not used by all mail clients.
     */
    public function addAttachment($file, $attachmentname, $mimetype, $description = '')
    {
        if (empty($mimetype)) {
            throw new Exception('Mime type for attachment not given.');
            return false;
        }

        if (!file_exists($file)) {
            throw new Exception('File does not exists.');
            return false;
        }

        if (empty($description) && !empty($attachmentname)) {
            $description = $attachmentname;
        }

        if (strlen($description) > self::MAILLINEMAXLENGTHBODY) {
            throw new Exception('Description too long.');
            return false;
        }

        $size = $this->realFileSize($file);
        if (!preg_match("/^[a-zA-Z0-9\s\.\-\'\\\\,\/]+$/", $description)) {
            $description = mb_encode_mimeheader($description, 'UTF-8', 'Q');
        }

        $this->attachments[$attachmentname] = array('file'=>$file, 'mime'=>$mimetype, 'description'=>$description, 'size'=>$size);
    }

   /**
    * Add a header to headers string. Checks for illegal characters and line length.
    * 
    * @param string $property
    * @param string $value
    * @param string $headers  The reference to the headers to added a new line to if valid.
    * @param int    $linelenlimit
    */
    private function addHeaderLine($property, $value, &$headers, $linelenlimit = self::MAILLINEMAXLENGTHHEADER)
    {
        if (strpos($value, ':') !== false) {
            error_log('Error header value contains illegal keyvalue seperator/":" character.');
            return;
        }

        if (strpos($value, "\r") !== false || strpos($value, "\n") !== false) {
            error_log('Error header value contains illegal enter character(s).');
            return;
        }

        $line = $property.': '.$value.CHRENTER;
        if (strlen($line) < $linelenlimit) {
            $headers .= $line;
        } else {
            error_log(sprintf('Header %1$s exceeded %2$d characters.', $property, $linelenlimit));
        }
    }

    /**
    * Convert a html message to text message without html tags.
    * 
    * @param string $htmlmessage
    * @return string Text message without html tags.
    */
    private function ConvertHtmlToText($htmlmessage)
    {
        $posstartbody = stripos($htmlmessage, '<body');
        if ($posstartbody !== false) {
            // Only use body of html document.
            $htmlmessage = substr($htmlmessage, $posstartbody);
        }

        // Convert html links to be insert als full url between [ ] characters, for a maximum of self::CONVMAILMAXLINKS.
        $nlinks = 0;
        $lenstartanchor = strlen('<a ');
        $lenendnchor = strlen('</a>');
        $posstartanchor = strrpos($htmlmessage, '<a ');
        $poscloseanchor = strpos($htmlmessage, '</a>', $posstartanchor + $lenstartanchor);
        while ($posstartanchor !== false && $poscloseanchor !== false && $nlinks < self::CONVMAILMAXLINKS) {
            ++$nlinks;
            $posendopenanchor = strpos($htmlmessage, '>', $posstartanchor);
            // Get the anchor attribute
            $anchorattrs = substr($htmlmessage, $posstartanchor + $lenstartanchor, $posendopenanchor - $posstartanchor - $lenstartanchor);
            // find the link href attribute.
            $posstartlinkattr = strpos($anchorattrs, 'href="') + 6;
            $posenlinkattr = strpos($anchorattrs, '"', $posstartlinkattr);
            $link = substr($anchorattrs, $posstartlinkattr, $posenlinkattr-$posstartlinkattr);
            $link = rawurldecode($link);
            if (filter_var($link, FILTER_VALIDATE_URL)) {
                // insert the link after the close anchor tag
                $posafterendachor = $poscloseanchor + $lenendnchor;
                $prehtmlmessage = substr($htmlmessage, 0, $posafterendachor);
                $posthtmlmessage = substr($htmlmessage, $posafterendachor);
                $htmlmessage = $prehtmlmessage.'['.$link.']'.$posthtmlmessage;
            }

            // find new anchor
            $posfromend = -(strlen($htmlmessage)-$posstartanchor)-1;
            $posstartanchor = strrpos($htmlmessage, '<a ',$posfromend);
            $poscloseanchor = strpos($htmlmessage, '</a>', $posstartanchor);
        }

        // Replace HTML <br> tags with enter characters.
        $htmlmessage = str_ireplace(array('<br>', '<br />', '<br/>'), CHRENTER, $htmlmessage);
        // Replace HTML <em> and <b> tags with * characters.
        $htmlmessage = str_ireplace(array('<em>', '</em>', '<b>', '</b>', '<h1>', '</h1>', '<h2>', '</h2>', '<h3>', '</h3>'), '*', $htmlmessage);
        // Replace HTML <u> tags with _ characters.
        $htmlmessage = str_ireplace(array('<u>', '</u>'), '_', $htmlmessage);
        // Replace HTML <i> tags with / characters.
        $htmlmessage = str_ireplace(array('<i>', '</i>'), '/', $htmlmessage);
        // Now strip all html tages. Fixme: creates new blank lines.
        $htmlmessage = strip_tags($htmlmessage);
        // Remove html ingored space and tabs and for plaintext.
        $htmlmessage = str_replace(array('  ', "\t"), '', $htmlmessage);
        // Replace &nbsp; with space.
        return str_ireplace('&nbsp;', ' ', $htmlmessage);
    }

    /**
     * Generate a boundary for multipart messages fast.
     * There are no requirement for the need for a secure pseudo random boundary value at all.
     *
     * @param string $previousBoundary
     * @param int    $lenBoundary      Shorter boundary means slightly shorter message but higher change of collisions.
     * @return string The new boundary value. It should never be the same are previous generated boundaries for current mail.
     */
    private function GenerateBoundary($previousBoundary, $lenBoundary = 32)
    {
        if ($lenBoundary <= 0) {
            return;
        }

        $lenMessageDigest = null;
        $newBoundary = '';
        $remainingLenBoundary = $lenBoundary;
        while ($remainingLenBoundary > 0) {
            $newBoundaryPart = hash('crc32b', mt_rand(0, PHP_INT_MAX), false);
            if (is_null($lenMessageDigest)) {
                $lenMessageDigest = strlen($newBoundaryPart);
            }

            if ($remainingLenBoundary < $lenMessageDigest) {
                $newBoundaryPart = substr($newBoundaryPart, 0, $remainingLenBoundary);
            }

            $newBoundary .= strtoupper($newBoundaryPart);
            $remainingLenBoundary -= $lenMessageDigest;
        }

        if ($newBoundary === $previousBoundary) {
            $newBoundary = GenerateBoundary($previousBoundary, $lenBoundary);
        }

        return $newBoundary;
    }

    /**
     * Check if from/to name does not contain illegal characters used for specifing the email address.
     *
     * @param string $fromName The from name to check on illegal characters.
     */
    private function isValidName($fromName)
    {
        if (strpos($fromName, '<') !== false ||
            strpos($fromName, '>') !== false) {
            return false;
        }

        return true;
    }
}
