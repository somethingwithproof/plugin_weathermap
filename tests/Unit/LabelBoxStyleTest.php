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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class LabelBoxStyleTest extends TestCase {
	private const MAP = <<<'CONF'
WIDTH 320
HEIGHT 240
BGCOLOR 255 255 255
KEYPOS DEFAULT 210 150 Load
KEYBGCOLOR none
KEYOUTLINECOLOR 0 0 200
%s
NODE DEFAULT
	LABELBGCOLOR 200 0 0
	LABELOUTLINECOLOR 0 0 200
NODE a
	LABEL AAAA
	POSITION 60 40
NODE b
	LABEL BBBB
	POSITION 260 40
NODE c
	LABEL CC
	LABELOUTLINECOLOR none
	POSITION 60 200
NODE d
	LABEL DD
	LABELBGCOLOR none
	POSITION 160 200
LINK ab
	NODES a b
	BANDWIDTH 100M
	TARGET static:50M:50M
NODE e
	LABEL EEEE
	LABELANGLE 90
	POSITION 290 160
LINK ad
	NODES a d
	BANDWIDTH 100M
	BWSTYLE angled
	TARGET static:50M:50M
CONF;

	/**
	 * Render the fixture map and return it with its decoded image.
	 *
	 * @return array{0: WeatherMap, 1: GdImage}
	 */
	private function render(string $hints, bool $selectB = false): array {
		$directory = sys_get_temp_dir() . '/wm-labelbox-' . bin2hex(random_bytes(6));
		mkdir($directory);

		try {
			file_put_contents($directory . '/map.conf', sprintf(self::MAP, $hints));
			$map          = new WeatherMap();
			$map->rrdtool = PHP_BINARY;
			self::assertTrue($map->ReadConfig($directory . '/map.conf'));
			$map->ReadData();
			$map->nodes['b']->selected = $selectB;
			$map->DrawMap($directory . '/map.png');
			$image = imagecreatefrompng($directory . '/map.png');
			self::assertInstanceOf(GdImage::class, $image);

			return [$map, $image];
		} finally {
			array_map('unlink', glob($directory . '/*'));
			rmdir($directory);
		}
	}

	/**
	 * @return array{0: int, 1: int, 2: int}
	 */
	private function rgb(GdImage $image, float $x, float $y): array {
		$colour = imagecolorsforindex($image, imagecolorat($image, (int) round($x), (int) round($y)));

		return [$colour['red'], $colour['green'], $colour['blue']];
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testHintsAreOffByDefaultAndClamped(): void {
		chdir(dirname(__DIR__, 2));
		require_once 'lib/WeatherMap.class.php';
		$item = new WeatherMapItem();
		self::assertSame(['radius' => 0, 'shadow' => false], wm_label_box_style($item));
		self::assertFalse(wm_label_box_is_styled(wm_label_box_style($item)));
		$item->add_hint('label_radius', '40');
		$item->add_hint('label_shadow', '1');
		self::assertSame(['radius' => 12, 'shadow' => true], wm_label_box_style($item));
		$item->add_hint('label_radius', 'round');
		$item->add_hint('label_shadow', 'yes');
		self::assertSame(['radius' => 0, 'shadow' => false], wm_label_box_style($item));
		$item->add_hint('label_radius', '-3');
		self::assertSame(0, wm_label_box_style($item)['radius']);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testRoundedOutlineStaysInsideItsBox(): void {
		chdir(dirname(__DIR__, 2));
		require_once 'lib/WeatherMap.class.php';
		self::assertSame([10, 20, 50, 20, 50, 40, 10, 40], wm_rounded_box_points(10, 20, 50, 40, 0));
		// An oversized radius is clamped to half the shorter side.
		$points = wm_rounded_box_points(10, 20, 50, 40, 100);
		self::assertCount(40, $points);

		foreach ($points as $index => $value) {
			$index % 2 === 0 ? self::assertThat($value, self::logicalAnd(self::greaterThanOrEqual(10 - 1e-9), self::lessThanOrEqual(50 + 1e-9)))
				: self::assertThat($value, self::logicalAnd(self::greaterThanOrEqual(20 - 1e-9), self::lessThanOrEqual(40 + 1e-9)));
		}

		self::assertEqualsWithDelta([40, 20], array_slice($points, 0, 2), 1e-9);
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testStyledLabelsAreRoundedPaddedAndShadowed(): void {
		chdir(dirname(__DIR__, 2));
		require_once 'lib/WeatherMap.class.php';
		[$map, $image]       = $this->render("SET label_radius 6\nSET label_shadow 1");
		[$x1, $y1, $x2, $y2] = $map->nodes['a']->boundingboxes[0];
		[$textWidth]         = $map->myimagestringsize($map->nodes['a']->labelfont, 'AAAA');

		self::assertEqualsWithDelta($textWidth + 14, $x2 - $x1, 1.0, 'styled boxes add horizontal padding');
		self::assertSame([255, 255, 255], $this->rgb($image, $x1, $y1), 'corner is cut away');
		self::assertSame([200, 0, 0], $this->rgb($image, $x1 + 3, ($y1 + $y2) / 2 - 3), 'fill inside the outline');
		[$red, $green, $blue] = $this->rgb($image, $x1, ($y1 + $y2) / 2 - 3);
		self::assertGreaterThan($red, $blue, 'outline on the straight edge');
		[$shadow] = $this->rgb($image, $x1 + 6, $y2 + 2);
		self::assertLessThan(250, $shadow, 'shadow below the box');
		// The padded node image must still be placed so the box lands on its bounding box.
		self::assertSame([200, 0, 0], $this->rgb($image, $x2 - 4, ($y1 + $y2) / 2 + 3));

		// A label without a background casts no shadow; its outline is still drawn.
		[$dx1, $dy1, $dx2, $dy2] = $map->nodes['d']->boundingboxes[0];
		self::assertSame([255, 255, 255], $this->rgb($image, ($dx1 + $dx2) / 2, $dy2 + 3));
		[$red, $green, $blue] = $this->rgb($image, ($dx1 + $dx2) / 2, $dy1);
		self::assertGreaterThan(150, $blue);
		// A filled label without an outline keeps its fill to the straight edge.
		[$cx1, $cy1, $cx2, $cy2] = $map->nodes['c']->boundingboxes[0];
		self::assertSame([200, 0, 0], $this->rgb($image, ($cx1 + $cx2) / 2, $cy1 + 1));
		// A vertical label swaps the padding axes.
		[$ex1, $ey1, $ex2, $ey2] = $map->nodes['e']->boundingboxes[0];
		self::assertEqualsWithDelta($textWidth + 14, $ey2 - $ey1, 1.0);
		// Angled bandwidth labels follow the link, so their rounded box is rotated too.
		$angled = array_values(array_filter($map->imap->shapes, fn ($shape) => str_starts_with($shape->name, 'LINK:L' . $map->links['ad']->id . ':')));
		self::assertNotEmpty($angled);
		self::assertInstanceOf(HTML_ImageMap_Area_Polygon::class, $angled[0]);
		// The selected highlight keeps the editor's square marker.
		[, $image]   = $this->render("SET label_radius 6\nSET label_shadow 1", true);
		[$bx1, $by1] = $map->nodes['b']->boundingboxes[0];
		self::assertSame([255, 0, 0], $this->rgb($image, $bx1, $by1));
	}

	#[RunInSeparateProcess]
	#[PreserveGlobalState(false)]
	public function testMapsWithoutHintsKeepSquareUnshadowedLabels(): void {
		chdir(dirname(__DIR__, 2));
		require_once 'lib/WeatherMap.class.php';
		[$map, $image]       = $this->render('', true);
		[$x1, $y1, $x2, $y2] = $map->nodes['a']->boundingboxes[0];
		[$textWidth]         = $map->myimagestringsize($map->nodes['a']->labelfont, 'AAAA');

		self::assertEqualsWithDelta($textWidth + 4, $x2 - $x1, 1.0);
		self::assertSame([0, 0, 200], $this->rgb($image, ceil($x1), ceil($y1)), 'square outlined corner');
		self::assertSame([255, 255, 255], $this->rgb($image, $x1 + 6, $y2 + 3), 'no shadow');
		[$bx1, $by1] = $map->nodes['b']->boundingboxes[0];
		self::assertSame([255, 0, 0], $this->rgb($image, ceil($bx1), ceil($by1)), 'selected marker');
		[$dx1, $dy1, $dx2] = $map->nodes['d']->boundingboxes[0];
		self::assertSame([0, 0, 200], $this->rgb($image, ($dx1 + $dx2) / 2, ceil($dy1)));
	}
}
