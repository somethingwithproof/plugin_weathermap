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
 * Pixels a node image is widened by on each side so a label shadow is not clipped.
 */
const WM_LABEL_SHADOW_MARGIN = 4;

/**
 * Read the map-wide label box style from SET label_radius and SET label_shadow.
 *
 * Both are off unless a map sets them, so existing maps keep their exact rendering.
 *
 * @param object $map Any object with get_hint().
 *
 * @return array{radius: int, shadow: bool}
 */
function wm_label_box_style($map) {
	$radius = $map->get_hint('label_radius', '0');

	return [
		'radius' => is_numeric($radius) ? max(0, min(12, (int) $radius)) : 0,
		'shadow' => (string) $map->get_hint('label_shadow', '0') === '1'
	];
}

/**
 * Whether a label box style differs from the legacy square, unshadowed box.
 *
 * @param array{radius: int, shadow: bool} $style
 *
 * @return bool
 */
function wm_label_box_is_styled($style) {
	return $style['radius'] > 0 || $style['shadow'];
}

/**
 * Outline of a rectangle with circular corners, clockwise from the top edge.
 *
 * @param float $x1
 * @param float $y1
 * @param float $x2
 * @param float $y2
 * @param float $radius Clamped so opposite corners never overlap.
 *
 * @return array<int, float> Flat x,y list.
 */
function wm_rounded_box_points($x1, $y1, $x2, $y2, $radius) {
	$radius = min($radius, ($x2 - $x1) / 2, ($y2 - $y1) / 2);

	if ($radius <= 0) {
		return [$x1, $y1, $x2, $y1, $x2, $y2, $x1, $y2];
	}

	$points  = [];
	$corners = [
		[$x2 - $radius, $y1 + $radius, -90],
		[$x2 - $radius, $y2 - $radius, 0],
		[$x1 + $radius, $y2 - $radius, 90],
		[$x1 + $radius, $y1 + $radius, 180]
	];

	foreach ($corners as [$cx, $cy, $start]) {
		for ($step = 0; $step <= 4; $step++) {
			$angle    = deg2rad($start + $step * 22.5);
			$points[] = $cx + $radius * cos($angle);
			$points[] = $cy + $radius * sin($angle);
		}
	}

	return $points;
}

/**
 * Fill a polygon with antialiased edges.
 *
 * GD antialiases lines but not filled polygons, so the shape is drawn at four
 * times the size on a transparent canvas and resampled onto the target.
 *
 * @param GdImage           $image
 * @param array<int, float> $points Flat x,y list in target coordinates.
 * @param array<int, int>   $rgb
 * @param int               $alpha  GD alpha: 0 is opaque, 127 is transparent.
 *
 * @return void
 */
function wm_fill_smooth_polygon($image, $points, $rgb, $alpha = 0) {
	$scale = 4;
	$xs    = [];
	$ys    = [];

	foreach ($points as $index => $value) {
		if ($index % 2 === 0) {
			$xs[] = $value;
		} else {
			$ys[] = $value;
		}
	}

	$left   = (int) floor(min($xs)) - 1;
	$top    = (int) floor(min($ys)) - 1;
	$width  = (int) ceil(max($xs)) + 1 - $left;
	$height = (int) ceil(max($ys)) + 1 - $top;
	$canvas = imagecreatetruecolor($width * $scale, $height * $scale);
	// The canvas is a few pixels larger than one label, so allocation does not fail in practice.
	assert($canvas instanceof GdImage);

	imagealphablending($canvas, false);
	imagesavealpha($canvas, true);
	// A transparent fill in the same colour keeps resampled edges free of dark fringes.
	imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], 127));

	$scaled = [];

	foreach ($points as $index => $value) {
		$scaled[] = (int) round(($value - ($index % 2 === 0 ? $left : $top)) * $scale);
	}

	imagefilledpolygon($canvas, $scaled, (int) imagecolorallocatealpha($canvas, $rgb[0], $rgb[1], $rgb[2], $alpha));
	imagealphablending($image, true);
	imagecopyresampled($image, $canvas, $left, $top, 0, 0, $width, $height, $width * $scale, $height * $scale);
}

/**
 * Draw a label background with the map's corner radius and optional shadow.
 *
 * The box is rotated by $angle radians about ($cx, $cy), matching rotateAboutPoint().
 * An outline is drawn as an outer fill with the background inset by one pixel,
 * so curved edges stay antialiased.
 *
 * @param GdImage                          $image
 * @param float                            $x1
 * @param float                            $y1
 * @param float                            $x2
 * @param float                            $y2
 * @param array{radius: int, shadow: bool} $style
 * @param array<int, int>|null             $fill    Background colour, or null for none.
 * @param array<int, int>|null             $outline Outline colour, or null for none.
 * @param float                            $angle
 * @param float                            $cx
 * @param float                            $cy
 *
 * @return array<int, float> The outer outline points, for image map areas.
 */
function wm_draw_label_box($image, $x1, $y1, $x2, $y2, $style, $fill, $outline, $angle = 0.0, $cx = 0.0, $cy = 0.0) {
	$shape = function ($inset, $dx = 0, $dy = 0) use ($x1, $y1, $x2, $y2, $style, $angle, $cx, $cy) {
		$points = wm_rounded_box_points($x1 + $inset, $y1 + $inset, $x2 - $inset, $y2 - $inset, max(0, $style['radius'] - $inset));

		if ($angle != 0) {
			rotateAboutPoint($points, $cx, $cy, $angle);
		}

		foreach ($points as $index => $value) {
			$points[$index] = $value + ($index % 2 === 0 ? $dx : $dy);
		}

		return $points;
	};

	if ($style['shadow'] && $fill !== null) {
		// Stacked translucent layers, faintest at the widest, approximate a soft shadow cast from above.
		foreach ([[1, 118], [0, 114], [-1, 108]] as [$spread, $alpha]) {
			wm_fill_smooth_polygon($image, $shape(-$spread, 0, 2 + $spread), [15, 23, 42], $alpha);
		}
	}

	$outer = $shape(0);

	if ($fill !== null && $outline !== null) {
		wm_fill_smooth_polygon($image, $outer, $outline);
		wm_fill_smooth_polygon($image, $shape(1), $fill);
	} elseif ($fill !== null) {
		wm_fill_smooth_polygon($image, $outer, $fill);
	} elseif ($outline !== null) {
		$ints = array_map(function ($value) {
			return (int) round($value);
		}, $outer);
		imagepolygon($image, $ints, myimagecolorallocate($image, $outline[0], $outline[1], $outline[2]));
	}

	return $outer;
}
