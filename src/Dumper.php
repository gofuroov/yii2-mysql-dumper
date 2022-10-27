<?php

namespace gofuroov\dumper\mysql;

use Ifsnop\Mysqldump\Mysqldump;
use yii\console\Application;
use yii\console\Controller;
use yii\console\Exception;
use yii\helpers\Console;

/**
 * Mysql database dumper by Olimjon Gofurov
 */
class Dumper extends Controller implements \yii\base\BootstrapInterface
{
    public string $name = 'database';
    public string $append_name = '';
    public string $path = "@runtime";
    public bool $send_via_telegram = false;
    public string $bot_token = '';
    public string $chat_id = '';
    public bool $delete_after_send = true;

    /**
     * @inheritDoc
     */
    public function bootstrap($app)
    {
        if ($app instanceof Application) {
            $app->controllerMap['dumper'] = [
                'class' => self::class,
                'name' => $this->name,
                'append_name' => $this->append_name,
                'path' => $this->path,
                'send_via_telegram' => $this->send_via_telegram,
                'bot_token' => $this->bot_token,
                'chat_id' => $this->chat_id,
                'delete_after_send' => $this->delete_after_send
            ];
        }
    }

    /**
     * Dump mysql database
     *
     * @return void
     * @throws \Exception
     */
    public function actionIndex(): void
    {
        $filename = \Yii::getAlias($this->path) . '/' . $this->name . $this->append_name;

        /**
         * Dumping
         */
        Console::output("Start dumping...");
        $sql_file = $this->export($filename . '.sql');
        Console::output("Dumping finished.\n");

        /**
         * Zipping
         */
        Console::output("Start zipping...");
        $zip_file = $this->createZip($sql_file, $filename . '.zip');
        Console::output("Zipping finished.\n");


        if ($this->send_via_telegram) {
            /**
             * Sending via telegram
             */
            Console::output("Start sending via telegram...");
            $this->sendViaTelegram($zip_file);
            Console::output("Sending finished.\n");

            if ($this->delete_after_send) {
                /**
                 * Deleting files
                 */
                Console::output("Start deleting files...");
                unlink($sql_file);
                unlink($zip_file);
                Console::output("Deleting finished.\n");
            }
        }
    }

    /**
     * @param $filename
     * @return string
     * @throws \Exception
     */
    protected function export($filename): string
    {
        $dump = new Mysqldump(\Yii::$app->db->dsn, \Yii::$app->db->username, \Yii::$app->db->password);
        $dump->start($filename);
        return $filename;
    }

    /**
     * @param $filename
     * @return void
     */
    protected function sendViaTelegram($filename): void
    {
        $url = 'https://api.telegram.org/bot' . $this->bot_token . '/sendDocument?chat_id=' . $this->chat_id;
        $finfo = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $filename);
        $cFile = new \CURLFile($filename, $finfo);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_TIMEOUT => 3 * 60, //3 minutes
            CURLOPT_POSTFIELDS => [
                "document" => $cFile
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
    }

    /**
     * @param string $file
     * @param string $zip_file
     * @return string
     * @throws Exception
     */
    public function createZip(string $file, string $zip_file): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($zip_file, \ZipArchive::CREATE) !== true) {
            throw new Exception("Cannot create zip file: {$zip_file}");
        }
        $zip->addFile($file, basename($file));
        if (!$zip->close()) {
            throw new Exception("Error in closing zip file: {$zip_file}");
        }
        return $zip_file;
    }
}