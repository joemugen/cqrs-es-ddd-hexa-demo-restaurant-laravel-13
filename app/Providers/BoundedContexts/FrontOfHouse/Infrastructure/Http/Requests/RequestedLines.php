<?php

declare(strict_types=1);

namespace App\Providers\BoundedContexts\FrontOfHouse\Infrastructure\Http\Requests;

use App\Providers\BoundedContexts\FrontOfHouse\Domain\MenuItemId;
use App\Providers\BoundedContexts\FrontOfHouse\Domain\RequestedLine;
use Illuminate\Http\Request;

/**
 * The lines a guest asks for, as an order and a revision both take them.
 */
final class RequestedLines
{
    /**
     * @return list<RequestedLine>
     */
    public static function from(Request $request): array
    {
        $validated = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.menuItemId' => ['required', 'string'],
            'lines.*.quantity' => ['required', 'integer'],
            'lines.*.course' => ['required', 'string'],
        ]);

        return array_map(fn (array $line): RequestedLine => new RequestedLine(
            new MenuItemId($line['menuItemId']),
            (int) $line['quantity'],
            $line['course'],
        ), $validated['lines']);
    }
}
