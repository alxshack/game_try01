<?php

class Logger {
    private static string $logDir = __DIR__ . '/../logs';

    /**
     * Записывает сообщение в лог-файл.
     * Имя файла соответствует формату: NAME_YYYY-MM-DD.log
     *
     * @param string $name Имя лога (префикс файла)
     * @param string $message Сообщение для логирования
     * @param string $level Уровень логирования (INFO, ERROR, DEBUG и т.д.)
     */
    public static function log(string $name, string $message, string $level = 'INFO'): void {
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0777, true);
        }

        $date = date('Y-m-d');
        $time = date('H:i:s');
        $filename = self::$logDir . '/' . $name . '_' . $date . '.log';
        
        $formattedMessage = sprintf("[%s] [%s] %s\n", $time, $level, $message);
        
        file_put_contents($filename, $formattedMessage, FILE_APPEND);
    }

    /**
     * Сокращенный метод для логирования ошибок.
     */
    public static function error(string $name, string $message): void {
        self::log($name, $message, 'ERROR');
    }

    /**
     * Сокращенный метод для информационных сообщений.
     */
    public static function info(string $name, string $message): void {
        self::log($name, $message, 'INFO');
    }
}
