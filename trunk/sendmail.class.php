<?php

/**
 *  Прием/обработка/отправка писем. Класс отправки писем.
 *  В этом файле не производится никакого вывода шаблонов (исключение составляет
 *  подключение java-script {@link AddJs()} или {@link AddCss()}, в обязательном порядке необходимых
 *  для работы этой библиотеки или модуля
 */

/**
 * Класс для отправки писем.
 *
 * @package Modules
 */
class ml_Mail
{

	/** Заголовки письма */
	public $head = array();

	public function __construct($from = '')
	{
		$this->ml_Mail($from);
	}

	/**
	 *  Конструктор класса.
	 *  Создает основные заголовки письма
	 *  @access public
	 *  @param string $from Email отправителя
	 *  @param string $enc Кодировка письма
	 *  @param integer $priority Приоритет письма (1..5)
	 *  @return void
	 */
	public function ml_Mail($from = '', $enc = 'UTF-8', $priority = 3)
	{

		$from = (string)$from;
		if (!$from)
			$from = "robot@example.com";

		$priority = (integer)$priority;

		$this->enc = (string)$enc;

		$this->bound = '---ALT_freeman==' . substr(md5(microtime() . mt_rand(1, 10000) . getmypid() . mt_rand(1, 10000)), 0, 12);

		$this->head = array();

		if ($from)
		{
			$this->head[] = 'From: ' . $this->encode($from);
			$this->head[] = 'Reply-To: ' . $this->encode($from);
		}

		$this->head[] = 'MIME-Version: 1.0';

		$gm = date('H') - gmdate('H');
		$this->head[] = 'Date: ' . gmdate('D, d M Y H:i:s ') . ($gm >= 0 ? '+' : '-') . sprintf('%02d00', abs($gm));
		$this->head[] = 'X-Priority: ' . $priority;
		$this->head[] = 'X-SendMachine: Re-Tracker.ru Mail Class';
		$this->head[] = 'Content-Type: multipart/alternative;' . "\n" . '     boundary="' . $this->bound . '"';

		$this->attachment = array();
		$this->to = $this->subj = $this->body = $this->heads = '';

	}

	/**
	 *  Прикрепляет к письму файл с указаным содержимым.
	 *  @access public
	 *  @param string $name Имя файла
	 *  @param string $data Бинарное содержимое файла
	 *  @param string $mime MIME-тип файла
	 *  @return boolean Файл присоединен
	 */
	public function attach($name, $data, $mime = 'application/octet-stream')
	{

		if (!$data || !$name)
			return false;

		$this->attachment[$name] = array($mime, $data);
		return true;
	}

	/**
	 *  Прикрепляет к письму указаный файл.
	 *  @access public
	 *  @param integer|string $file Полный путь к файлу или ID файла, уже отмеченого в системе.
	 *  @param string $mime MIME-тип файла
	 *  @return boolean Файл присоединен
	 */
	public function attachFile($file, $mime = 'unknown/unknown')
	{

		if (is_file($file))
		{
			$name = basename($file);

			if (!($data = @ file_get_contents($file)))
				return false;

		}
		else
		{
			return false;
		}

		return $this->attach($name, $data, $mime);

	}

	/**
	 *  Добавление темы письма.
	 *  @access private
	 *  @param string $subj Текст темы
	 *  @return string Кодированный текст в соответствии с кодировкой письма
	 */
	private function encode($text)
	{

		$text = (string)$text;
		$parts = $this->getNotEnglish($text);

		for ($n = (integer)sizeof($parts) - 1, $i = $n; $i >= 0; $i--)
		{
			if (!sizeof($parts))
				continue;

			$cur = & $parts[$i];
			$str = '=?' . $this->enc . '?B?' . base64_encode(substr($text, $cur[0], $cur[1])) . '?=';
			$text = substr($text, 0, $cur[0]) . $str . substr($text, $cur[0] + $cur[1]);
		}

		return $text;

	}

	/**
	 *  Возвращает найденные в строке диапазоны неанглийских символов.
	 *  @access private
	 *  @param string $text Текст
	 *  @return array Array( [] => Array(10,3),  )
	 */
	private function getNotEnglish($text)
	{
		$text = (string)$text;

		$mass = array();
		$e = $p = 0;
		$f = false;
		for ($i = 0, $s = strlen($text); $i < $s; $i++)
		{

			$symb = ord($text[$i]);

			/** Отбиваем начало  */
			if (($symb > 127) && $f === false)
				$f = $i;

			if ($f === false)
				continue;

			/** Отбиваем продолжение, если не предполагаемое продолжение */
			if ($symb >= 127)
			{
				/** Если были пробелы */
				if ($p)
				{
					$e += $p + 1;
					$p = 0;
				}
				else
					/** Если небыло пробелов */
					$e++;
				continue;
			}

			/** Отбиваем предполагаемое продолжение */
			if ($symb < 65)
			{
				$p++;
				continue;
			}

			/** Если окончились неанглийские буквы, то запоминаем отрезок */
			if ($symb >= 65 && $symb < 127)
			{
				$mass[] = array($f, $e);
				$e = $p = 0;
				$f = false;
			}

		}

		/** Если окончились неанглийские буквы, то запоминаем отрезок */
		if ($f)
			$mass[] = array($f, $e);
		/** Если всё слово русское */
		elseif (!$f && $e)
		{
			$mass[0][0] = $f;
			$mass[0][1] = $e;
		}

		unset($e, $f, $p, $symb, $i, $s);

		return $mass;
	}

	/**
	 *  Создает письмо.
	 *
	 *  @access public
	 *
	 *  @param string $subj Тема письма
	 *  @param string $html HTML-текст письма
	 *  @param string $plain plain-текст письма
	 *  @param boolean $noplain Plain-текст письма отсутствует
	 *  @return void
	 */
	public function make($subj = '', $html = '', $plain = '', $noplain = false)
	{

		$body = "\n\n";

		if (!$plain && $html && !$noplain)
		{
			//
			$plain = $html;

			/** Убираем переносы строк */
			$plain = str_replace("\n", '', $plain);
			$plain = str_replace("\r", '', $plain);

			/** Заменяем ссылки на текст и ссылку рядом */
			$plain = preg_replace('#<a .*? href=["\']?([^"\' ]*).*?([^>]*)</a>#is', '\\2 (\\1)', $plain);
			/** Заменяем картинки на текст и ссылку рядом */
			$plain = preg_replace('#(<img .*? src=["\']?)([^"\' ]*)(["\' ])#is', '(\\2)\\1\\2\\3', $plain);
			/** Заменяем <b> на * */
			$plain = preg_replace('#<b.*?>(.*?)</b>#is', '*\\1*', $plain);
			/** Заменяем <i> на _ */
			$plain = preg_replace('#<i.*?>(.*?)</i>#is', '_\\1_', $plain);

			/** Добавляем переносы строк */
			$plain = str_replace('<tr', "\n" . '<tr', $plain);
			$plain = str_replace('<table', "\n" . '<table', $plain);
			$plain = str_replace('<div', "\n" . '<div', $plain);
			$plain = str_replace('<p', "\n" . '<p', $plain);
			$plain = str_replace('<pre', "\n" . '<pre', $plain);
			$plain = str_replace('</tr', "\n" . '</tr', $plain);
			$plain = str_replace('</table', "\n" . '</table', $plain);
			$plain = str_replace('</div', "\n" . '</div', $plain);
			$plain = str_replace('</p', "\n" . '</p', $plain);
			$plain = str_replace('</pre', "\n" . '</pre', $plain);
			$plain = str_replace('<br', "\n" . '<br', $plain);

			/** Убираем прочие теги */
			$plain = strip_tags($plain);
		}

		if ($plain)
		{
			$body .= '--' . $this->bound . "\n";
			$body .= 'Content-Type: text/plain; charset=' . $this->enc . "\n";
			$body .= 'Content-Transfer-Encoding: 8bit' . "\n\n";
			$body .= $plain . "\n\n";
		}

		if ($html)
		{
			$body .= '--' . $this->bound . "\n";
			$body .= 'Content-Type: text/html; charset=' . $this->enc . "\n";
			$body .= 'Content-Transfer-Encoding: 8bit' . "\n\n";
			$body .= $html . "\n\n";
		}

		foreach ($this->attachment as $name => $data)
		{
			$name = str_replace('"', '\'', $name);
			$body .= '--' . $this->bound . "\n";
			$body .= 'Content-Type: ' . $data[0] . '; name="' . $name . '"' . "\n";
			$body .= 'Content-transfer-encoding: base64' . "\n";
			$body .= 'Content-Disposition: attachment; filename="' . $name . '"' . "\n\n";
			$data[1] = base64_encode($data[1]);
			$data[1] = preg_replace('#(.{76})#u', '\\1' . "\n", $data[1]);
			$body .= $data[1] . "\n\n";
		}

		$body .= '--' . $this->bound . '--' . "\n";

		$this->subj = $this->encode($subj);
		$this->body = $body;

		unset($body, $name, $data, $subj);
	}

	/**
	 *  Отправить письмо.
	 *  @param string $to Email получателя
	 *  @return boolean Письмо отправлено
	 */
	public function send($to)
	{

		$to = $this->encode($to);
		$this->heads = implode("\n", $this->head);

		return (boolean)@ mail($to, $this->subj, $this->body, $this->heads);

	}

}