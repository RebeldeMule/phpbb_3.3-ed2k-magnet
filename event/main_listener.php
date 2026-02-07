<?php


/**
 *
 * ed2k. An extension for the phpBB Forum Software package.
 *
 * @copyright (c) 2024, RebeldeMule
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */

namespace rbm\ed2k\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
    /** @var \phpbb\template\template */
    protected $template;
    /** @var \phpbb\user */
    protected $user;
    protected $phpbb_root_path;
	protected $icon_url;
	private $msg_counter = 0; // Para IDs únicos por mensaje

	public function __construct(
        \phpbb\template\template $template,
        \phpbb\user $user,
		\phpbb\request\request_interface $request,
		$phpbb_root_path
	) {
		$this->template = $template;
		$this->user = $user;
		$this->user->add_lang_ext('rbm/ed2k', 'common');
		$template_vars = [
            'ED2K_MODAL_TITLE'       => $this->user->lang('ED2K_MODAL_TITLE'),
            'ED2K_MODAL_SEND'       => $this->user->lang('ED2K_MODAL_SEND'),
            'ED2K_MODAL_CLOSE'       => $this->user->lang('ED2K_MODAL_CLOSE'),
            'ED2K_MODAL_COPIED'       => $this->user->lang('ED2K_MODAL_COPIED'),
            'ED2K_MODAL_TEXTAREA_LABEL'       => $this->user->lang('ED2K_MODAL_TEXTAREA_LABEL'),
        ];
		$this->template->assign_vars($template_vars);

		// Obtener la ruta base del script mediante el servicio request
		$script_name = $request->server('SCRIPT_NAME', '');
		$script_dir = $script_name ? dirname($script_name) : '';
		$script_dir = rtrim($script_dir, '/\\');
		$base = $script_dir === '' ? '/' : $script_dir . '/';
		$this->phpbb_root_path = $phpbb_root_path;
		$this->icon_url = $base . 'ext/rbm/ed2k/styles/all/theme/images/';
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
		$this->msg_counter++;
		$msg_id = 'ed2k-magnet-' . $this->msg_counter;
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
			'<input type="checkbox" id="%s" class="ed2k-magnet-checkbox" data-raw="%s" />',
			$msg_id,
			$url_html
		);

		// Construcción del HTML final
		return sprintf(
			'<div class="contenedor-elink">%s <a href="%s" target="_blank"><i class="icon fa-bar-chart fa-fw ed2k-stats" aria-hidden="true"></i></a> <img src="%smule.gif" border="0" title="ed2k link" style="padding-top: 3px;" /><a href="%s" class="postlink">%s&nbsp;&nbsp;[%s]</a></div>',
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
		$this->msg_counter++;
		$msg_id = 'ed2k-magnet-' . $this->msg_counter;

		// Decodificar si el enlace viene urlencoded (por ejemplo desde posting_preview_ed2k)
		if (strpos($mf[1], '=') === false && strpos($mf[1], '%') !== false) {
			$mf[1] = urldecode($mf[1]);
		}

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
		// Construcción del checkbox
		$checkbox = sprintf(
			'<input type="checkbox" id="%s" class="ed2k-magnet-checkbox" data-raw="%s" />',
			$msg_id,
			$raw
		);
		
		// Para el enlace visible y el href, usamos el enlace procesado normalmente
		return "$checkbox <img src='{$this->icon_url}iman.gif' class='magnet-link' alt='Magnet' title='Torrent Magnet'> <a href='" . htmlspecialchars($magnet_link) . "' class=\"postlink\">" . htmlspecialchars($magnet_name) . "</a>";
	}


	private function procesar_ed2k($message)
	{
		// Decodificamos el enlace magnet para procesarlo
		$message = preg_replace_callback(
			'#\[url\]magnet:(.*?)\[/url\]#is',
			function ($matches) {
				return '[url]magnet:' . urlencode($matches[1]) . '[/url]';
			},
			$message
		);

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
			$message,
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
