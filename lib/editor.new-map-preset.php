<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group, Howard Jones                   |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Utilisation colours for new maps: calm cool tones at low load, warming to red near capacity.
 */
const WM_PRESET_SCALE = [
	[0, 0, 203, 213, 225],
	[0, 1, 226, 232, 240],
	[1, 10, 94, 234, 212],
	[10, 25, 45, 212, 191],
	[25, 40, 132, 204, 22],
	[40, 55, 250, 204, 21],
	[55, 70, 251, 146, 60],
	[70, 85, 239, 68, 68],
	[85, 100, 190, 18, 60]
];

/**
 * Apply readable initial defaults only to a newly created blank map.
 *
 * Existing maps are never restyled: these values are written to the new map's
 * configuration, and the rounded, shadowed label boxes are opt-in SET hints.
 *
 * @param WeatherMap $map
 *
 * @return void
 */
function wm_new_map_preset($map) {
	$map->add_hint('background_sizing', 'fit');
	$map->add_hint('traffic_pulse', '1');
	$map->add_hint('label_radius', '4');
	$map->add_hint('label_shadow', '1');
	$map->add_hint('antialias', '1');
	$map->width     = 1400;
	$map->height    = 750;
	$map->htmlstyle = 'overlib';

	$special = ['BG' => [248, 250, 252], 'KEYBG' => [255, 255, 255], 'KEYOUTLINE' => [226, 232, 240], 'KEYTEXT' => [30, 41, 59], 'TITLE' => [15, 23, 42], 'TIME' => [100, 116, 139]];

	foreach ($special as $key => [$red, $green, $blue]) {
		$map->colours['DEFAULT'][$key] = ['bottom' => -2, 'top' => -1, 'red1' => $red, 'green1' => $green, 'blue1' => $blue, 'special' => 1];
	}

	foreach (WM_PRESET_SCALE as [$bottom, $top, $red, $green, $blue]) {
		$key                           = $bottom . '_' . $top;
		$map->colours['DEFAULT'][$key] = ['bottom' => $bottom, 'top' => $top, 'red1' => $red, 'green1' => $green, 'blue1' => $blue, 'special' => 0, 'key' => $key];
	}

	// The 0-0 band colours idle links; the legend hides it, as it does for the built-in scale.
	$map->add_hint('key_hidezero_DEFAULT', '1');

	$node                     = $map->nodes['DEFAULT'];
	$node->labelbgcolour      = [255, 255, 255];
	$node->labeloutlinecolour = [203, 213, 225];
	$node->labelfontcolour    = [30, 41, 59];

	$link                    = $map->links['DEFAULT'];
	$link->width             = 6;
	$link->arrowstyle        = 'compact';
	$link->outlinecolour     = [71, 85, 105];
	$link->bwboxcolour       = [255, 255, 255];
	$link->bwoutlinecolour   = [203, 213, 225];
	$link->bwfontcolour      = [30, 41, 59];
	$link->commentfontcolour = [71, 85, 105];

	// Without FreeType or the bundled fonts, the built-in GD fonts stay in place.
	$regular = wm_new_map_preset_font($map, 'Vera.ttf', 9);

	if ($regular !== null) {
		$link->bwfont      = $regular;
		$link->commentfont = $regular;
		$map->keyfont      = $regular;
		$map->timefont     = $regular;
		$node->labelfont   = wm_new_map_preset_font($map, 'VeraBd.ttf', 9) ?? $regular;
		$map->titlefont    = wm_new_map_preset_font($map, 'VeraBd.ttf', 13) ?? $regular;
	}
}

/**
 * Register a bundled TrueType font, reusing an identical definition.
 *
 * @param WeatherMap $map
 * @param string     $name File name in docs/example.
 * @param int        $size
 *
 * @return int|null Font number, or null when FreeType or the file is unavailable.
 */
function wm_new_map_preset_font($map, $name, $size) {
	$file = realpath(__DIR__ . '/../docs/example/' . $name);

	if ($file === false || !is_readable($file) || !function_exists('imagettfbbox') || !function_exists('imagettftext') || !is_array(imagettfbbox($size, 0, $file, '1G'))) {
		return null;
	}

	$relative = 'docs/example/' . $name;

	foreach ($map->fonts as $number => $font) {
		if (is_object($font) && $font->type === 'truetype' && $font->file === $relative && (int) $font->size === $size) {
			return (int) $number;
		}
	}

	$number = 100;

	while (isset($map->fonts[$number])) {
		$number++;
	}

	$font                = new WMFont();
	$font->type          = 'truetype';
	$font->file          = $relative;
	$font->size          = $size;
	$map->fonts[$number] = $font;

	return $number;
}
