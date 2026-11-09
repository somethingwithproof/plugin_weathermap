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

beforeAll(function () {
	require_once dirname(__DIR__, 2) . '/lib/WeatherMap.functions.php';
	require_once dirname(__DIR__, 2) . '/lib/editor.new-map-preset.php';
});

it('UsesReadableLandscapeDefaultsAndAllocatesAnUnusedFontId', function () {
	$map = new class {
		public $width;
		public $height;
		public $htmlstyle;
		public $links;
		public $nodes;
		public $keyfont   = 4;
		public $titlefont = 2;
		public $timefont  = 2;
		public $colours   = ['DEFAULT' => []];
		public $fonts     = [100 => null];
		public $hints     = [];
		public function add_hint($name, $value) {
			$this->hints[$name] = $value;
		}
	};
	$map->links      = ['DEFAULT' => (object) ['arrowstyle' => 'classic', 'commentfontcolour' => [0, 0, 0], 'bwfont' => 2, 'commentfont' => 1]];
	$map->nodes      = ['DEFAULT' => (object) ['labelfont' => 3]];
	$map->fonts[100] = new WMFont();
	wm_new_map_preset($map);
	expect([$map->width, $map->height])->toBe([1400, 750])
		->and($map->htmlstyle)->toBe('overlib')
		->and($map->hints)->toMatchArray(['background_sizing' => 'fit', 'label_radius' => '4', 'label_shadow' => '1', 'antialias' => '1', 'key_hidezero_DEFAULT' => '1'])
		->and($map->links['DEFAULT']->arrowstyle)->toBe('compact')
		->and($map->links['DEFAULT']->width)->toBe(6)
		->and($map->links['DEFAULT']->commentfontcolour)->toBe([71, 85, 105])
		->and($map->links['DEFAULT']->bwfont)->toBe(101)
		->and($map->links['DEFAULT']->commentfont)->toBe(101)
		->and([$map->keyfont, $map->timefont])->toBe([101, 101])
		->and($map->nodes['DEFAULT']->labelfont)->toBe(102)
		->and($map->titlefont)->toBe(103)
		->and($map->fonts[101]->file)->toBe('docs/example/Vera.ttf')
		->and($map->fonts[101]->size)->toBe(9)
		->and([$map->fonts[102]->file, $map->fonts[102]->size])->toBe(['docs/example/VeraBd.ttf', 9])
		->and([$map->fonts[103]->file, $map->fonts[103]->size])->toBe(['docs/example/VeraBd.ttf', 13])
		->and(is_readable(wm_font_file($map->fonts[101]->file)))->toBeTrue();
	expect($map->colours['DEFAULT']['BG'])->toMatchArray(['red1' => 248, 'green1' => 250, 'blue1' => 252, 'special' => 1])
		->and($map->colours['DEFAULT']['85_100'])->toMatchArray(['bottom' => 85, 'top' => 100, 'red1' => 190, 'special' => 0, 'key' => '85_100'])
		->and(count(array_filter($map->colours['DEFAULT'], fn ($band) => $band['special'] === 0)))->toBe(count(WM_PRESET_SCALE));
});

it('ReusesAnIdenticalFontAndSkipsUnavailableOnes', function () {
	$map   = (object) ['fonts' => []];
	$first = wm_new_map_preset_font($map, 'Vera.ttf', 9);
	expect(wm_new_map_preset_font($map, 'Vera.ttf', 9))->toBe($first)
		->and(wm_new_map_preset_font($map, 'Vera.ttf', 11))->not->toBe($first)
		->and(wm_new_map_preset_font($map, 'Missing.ttf', 9))->toBeNull()
		->and($map->fonts)->toHaveCount(2);
});
