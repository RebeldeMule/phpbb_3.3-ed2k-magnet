<?php


/**
 *
 * ed2k. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2024, RebeldeMule
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 ***** OJO BUG **** Si un enlace magnet contiene alguna URL para añadir trackers,
 *					el enlace se romperá al mostrarlo en el foro.
 *					Esto es un fallo conocido y no tengo solución por ahora.
 *					La solución temporal de "No convertir automáticamente las URLs".
 *					Para ello he añadido 'S_MAGIC_URL_CHECKED' => ' checked', a $template en la extensión "publica" por defecto. @main_listener.php#65
 *					Y en la base de datos he puesto el campo enable_magic_url de la tabla phpbb3_posts a 0.
 */

namespace rbm\ed2k\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	protected $language;
    protected $phpbb_root_path;
	protected $icon_url;

	public function __construct(
		\phpbb\language\language $language,
		$phpbb_root_path
	) {
		$this->language = $language;
        $this->phpbb_root_path = $phpbb_root_path;
		$this->icon_url = $phpbb_root_path . "ext/rbm/ed2k/styles/all/theme/images/";
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.modify_text_for_display_after'    => 'viewtopic_ed2k',
			'core.modify_format_display_text_after' => 'posting_preview_ed2k',
		];
	}

	private function humanize_size($size, $rounder = 0)
	{
		$sizes = ['Bytes', 'Kb', 'Mb', 'Gb', 'Tb', 'Pb', 'Eb', 'Zb', 'Yb'];
		$rounders = [0, 1, 2, 2, 2, 3, 3, 3, 3];
		$i = 0;
		while ($size >= 1024 && $i < count($sizes) - 1) {
			$size /= 1024;
			$i++;
		}
		$rounder = $rounder ?: $rounders[$i];
		return sprintf('%.'.$rounder.'f %s', round($size, $rounder), $sizes[$i]);
	}

	private function ed2k_link_callback($m)
	{
		// Deserialización y limpieza básica
		$url = $m[2];
		$filename = rawurldecode($m[3]);
		$size_bytes = $m[4];
		$hash = $m[5];

		// Truncado de nombre (UTF-8 safe)
		$max_len = 100;
		if (mb_strlen($filename) > $max_len) {
			$filename = mb_substr($filename, 0, $max_len - 19) . '...' . mb_substr($filename, -16);
		}
		
		// Preparación de variables para la vista
		$filename_html = htmlspecialchars($filename);
		$url_html = htmlspecialchars($url);
		$size = $this->humanize_size($size_bytes);
		$stats_url = 'http://ed2k.shortypower.org/?hash=' . $hash;

		// Construcción del checkbox
		$checkbox = sprintf(
			'<input type="checkbox" class="ed2k-magnet-checkbox" data-raw="%s" />',
			$url_html
		);

		// Construcción del HTML final
		return sprintf(
			'<div class="contenedor-elink">%s <a href="%s" target="_blank"><i class="icon fa-bar-chart fa-fw ed2k-stats" aria-hidden="true"></i></a> <img src="%sdonkey.gif" border="0" title="donkey link" style="padding-top: 3px;" /><a href="%s" class="postlink">%s&nbsp;&nbsp;[%s]</a></div>',
			$checkbox,
			$stats_url,
			$this->icon_url,
			$url_html,
			$filename_html,
			$size
		);
	}

	private function magnet_callback($mf)
	{
		// Guardamos el enlace magnet original antes de cualquier procesamiento
		$original_magnet = 'magnet:' . $mf[1];
		
		// Decodificamos el enlace para procesarlo
		$magnet_link = str_replace(['&amp;', '&quot;'], ['&', '"'], $original_magnet);
		$magnet_rest = str_replace('magnet:?xt=urn:btih:', '', $magnet_link);
		$magnet_troz = explode("&", $magnet_rest);
		$magnet_name = '';
		$magnet_size = '';
		
		foreach ($magnet_troz as $valores) {
			if (strpos($valores, 'dn=') === 0) {
				$magnet_name = urldecode(substr($valores, 3));
			}
			if (strpos($valores, 'xl=') === 0) {
				$magnet_size = $this->humanize_size(substr($valores, 3));
			}
		}
		
		$magnet_size = $magnet_size ? "  [$magnet_size]" : '';
		$magnet_name = $magnet_name ? $magnet_name . $magnet_size : 'Enlace torrent magnético' . $magnet_size;
		
		// Usamos el enlace original para el data-raw, codificado para HTML pero sin procesar las URLs
		$raw = urldecode(str_replace('"', '&quot;', $magnet_link));
		$checkbox = "<input type='checkbox' class='ed2k-magnet-checkbox' data-raw=\"$raw\" />";
		
		// Para el enlace visible y el href, usamos el enlace procesado normalmente
		return "$checkbox <img src='{$this->icon_url}magnet.gif' alt='Magnet' title='Torrent Magnet'> <a href='" . htmlspecialchars($magnet_link) . "' class=\"postlink\">" . htmlspecialchars($magnet_name) . "</a>";
	}

	private $msg_counter = 0; // Para IDs únicos por mensaje

	private function procesar_ed2k($message)
	{
		$this->msg_counter++;
		$msg_id = 'ed2k-magnet-msg-' . $this->msg_counter;

		$patterns = [
			'#\[url\](ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?)\[/url\]#is',
			'#\[url=(ed2k://\|file\|(.*?)\|\d+\|\w+\|(h=\w+\|)?/?)\](.*?)\[/url\]#is'
		];
		$replacements = [
			'<a href="$1" class="postlink">$2</a>',
			'<a href="$1" class="postlink">$4</a>'
		];
		$message = preg_replace($patterns, $replacements, $message);
		$message = preg_replace_callback(
			"#(^|(?<=[^\w\"']))(ed2k://\|file\|([^\\/\|:<>\*\?\"]+?)\|(\d+?)\|([a-f0-9]{32})\|(.*?)/?)(?![\"'])(?=([,\.]*?[\s<\[])|[,\.]*?$)#i",
			[$this, 'ed2k_link_callback'],
			$message
		);
		$message = preg_replace_callback(
			"#\[url\]magnet:([^\[]+)\[/url\]#is",
			[$this, 'magnet_callback'],
			$message
		);
		return $message;
	}

	public function viewtopic_ed2k($event)
	{
		$event['text'] = $this->procesar_ed2k($event['text']);
	}

	public function posting_preview_ed2k($event)
	{
		$event['text'] = $this->procesar_ed2k($event['text']);
	}
}
