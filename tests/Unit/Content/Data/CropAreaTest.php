<?php

declare(strict_types=1);

use Made\CmsPackage\Content\Data\CropArea;

/**
 * A grid of width × height cells, each holding its own "column,row" in the upright original.
 *
 * @return list<list<string>>
 */
function cropGrid(int $width, int $height): array
{
    $grid = [];

    for ($row = 0; $row < $height; $row++) {
        for ($column = 0; $column < $width; $column++) {
            $grid[$row][$column] = "{$column},{$row}";
        }
    }

    return $grid;
}

/**
 * The grid as the editor sees it: turned clockwise by the rotation, then mirrored left to right.
 *
 * @param  list<list<string>>  $grid
 * @return list<list<string>>
 */
function cropView(array $grid, int $rotation, bool $flip): array
{
    for ($turn = 0; $turn < intdiv($rotation, 90); $turn++) {
        $height = count($grid);
        $width = count($grid[0]);
        $turned = [];

        for ($row = 0; $row < $width; $row++) {
            for ($column = 0; $column < $height; $column++) {
                $turned[$row][$column] = $grid[$height - 1 - $column][$row];
            }
        }

        $grid = $turned;
    }

    return $flip ? array_map(static fn (array $row): array => array_reverse($row), $grid) : $grid;
}

/**
 * @param  list<list<string>>  $grid
 * @return list<list<string>>
 */
function cropCut(array $grid, int $x, int $y, int $width, int $height): array
{
    return array_map(
        static fn (array $row): array => array_slice($row, $x, $width),
        array_slice($grid, $y, $height),
    );
}

it('turns the size of the original as the editor sees it', function (int $rotation, array $size): void {
    expect(CropArea::turned(4000, 2667, $rotation))->toBe($size);
})->with([
    'not turned' => [0, [4000, 2667]],
    'a quarter right' => [90, [2667, 4000]],
    'half' => [180, [4000, 2667]],
    'a quarter left' => [270, [2667, 4000]],
]);

it('cuts the original so that turning and mirroring the cut gives what the editor saw', function (int $rotation, bool $flip): void {
    $original = cropGrid(5, 3);
    $view = cropView($original, $rotation, $flip);
    [$viewWidth, $viewHeight] = CropArea::turned(5, 3, $rotation);

    expect([count($view[0]), count($view)])->toBe([$viewWidth, $viewHeight]);

    for ($y = 0; $y < $viewHeight; $y++) {
        for ($x = 0; $x < $viewWidth; $x++) {
            for ($height = 1; $y + $height <= $viewHeight; $height++) {
                for ($width = 1; $x + $width <= $viewWidth; $width++) {
                    $area = new CropArea($x, $y, $width, $height, $rotation, $flip);
                    $cut = $area->onOriginal(5, 3);

                    expect(cropView(cropCut($original, $cut['x'], $cut['y'], $cut['width'], $cut['height']), $rotation, $flip))
                        ->toBe(cropCut($view, $x, $y, $width, $height));
                }
            }
        }
    }
})->with([
    'not turned' => [0, false],
    'not turned, mirrored' => [0, true],
    'a quarter right' => [90, false],
    'a quarter right, mirrored' => [90, true],
    'half' => [180, false],
    'half, mirrored' => [180, true],
    'a quarter left' => [270, false],
    'a quarter left, mirrored' => [270, true],
]);

it('fits inside the original as the editor sees it', function (CropArea $area, bool $fits): void {
    expect($area->fits(400, 300))->toBe($fits);
})->with([
    'the whole original' => [new CropArea(0, 0, 400, 300), true],
    'to the right edge' => [new CropArea(100, 50, 300, 250), true],
    'a pixel too wide' => [new CropArea(100, 0, 301, 300), false],
    'a pixel too high' => [new CropArea(0, 1, 400, 300), false],
    'left of the original' => [new CropArea(-1, 0, 100, 100), false],
    'above the original' => [new CropArea(0, -1, 100, 100), false],
    'without width' => [new CropArea(0, 0, 0, 100), false],
    'without height' => [new CropArea(0, 0, 100, 0), false],
    'turned, as high as the original is wide' => [new CropArea(0, 0, 300, 400, 90), true],
    'turned, as wide as the original' => [new CropArea(0, 0, 400, 300, 90), false],
    'half turned' => [new CropArea(0, 0, 400, 300, 180, true), true],
    'turned a quarter left' => [new CropArea(0, 100, 300, 300, 270), true],
    'turned at an angle' => [new CropArea(0, 0, 100, 100, 45), false],
]);

it('knows the whole original as it is', function (CropArea $area, bool $original): void {
    expect($area->isOriginal(400, 300))->toBe($original);
})->with([
    'the whole original' => [CropArea::whole(400, 300), true],
    'a part' => [new CropArea(0, 0, 399, 300), false],
    'turned' => [new CropArea(0, 0, 300, 400, 90), false],
    'half turned' => [new CropArea(0, 0, 400, 300, 180), false],
    'mirrored' => [new CropArea(0, 0, 400, 300, 0, true), false],
]);

it('is stored as an array and read back', function (): void {
    $area = new CropArea(240, 344, 3520, 1980, 90, true);

    expect($area->toArray())->toBe(['x' => 240, 'y' => 344, 'width' => 3520, 'height' => 1980, 'rotation' => 90, 'flip' => true])
        ->and(CropArea::fromArray($area->toArray()))->toEqual($area)
        ->and(CropArea::fromArray([...$area->toArray(), 'scale_down' => false]))->toEqual($area);
});

it('reads nothing from an array that is no area', function (mixed $values): void {
    expect(CropArea::fromArray($values))->toBeNull();
})->with([
    'null' => [null],
    'a text' => ['0,0,10,10'],
    'without height' => [['x' => 0, 'y' => 0, 'width' => 10]],
    'a size as text' => [['x' => 0, 'y' => 0, 'width' => '10', 'height' => 10]],
    'a rotation at an angle' => [['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'rotation' => 45]],
    'a flip as text' => [['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'flip' => 'yes']],
]);

it('reads an area stored without a rotation or a flip as not turned and not mirrored', function (): void {
    expect(CropArea::fromArray(['x' => 1, 'y' => 2, 'width' => 3, 'height' => 4]))->toEqual(new CropArea(1, 2, 3, 4));
});
