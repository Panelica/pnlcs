<?php

use App\Models\TodoItem;
use App\Widgets\ToDoWidget;

/**
 * A to-do title rendered into the dashboard as markup.
 *
 * The widget escapes the due date and then writes the title straight into the
 * HTML beside it. The Support widget, two files away, escapes the ticket title
 * it renders in the same position.
 *
 * The to-do list is deliberately left without a permission - it is described as
 * an admin's own scratchpad - so every staff account can add an item, and the
 * dashboard is what every other admin opens first. A title carrying a script tag
 * therefore runs in another administrator's session, which is how a limited
 * account reaches a full one.
 */
function todoWithTitle(string $title): TodoItem
{
    return TodoItem::create([
        'title' => $title,
        'status' => 'pending',
        'due_date' => today()->addDay()->toDateString(),
    ]);
}

it('does not put a to-do title into the page as markup', function () {
    todoWithTitle('<script>alert(1)</script>');

    $widget = new ToDoWidget;
    $html = $widget->render($widget->getData());

    expect($html)->not->toContain('<script>alert(1)</script>')
        ->and($html)->toContain('&lt;script&gt;');
});

it('still shows an ordinary title', function () {
    todoWithTitle('Chase the failed backup');

    $widget = new ToDoWidget;
    $html = $widget->render($widget->getData());

    expect($html)->toContain('Chase the failed backup');
});

it('keeps a title with an ampersand readable', function () {
    todoWithTitle('Invoices & renewals');

    $widget = new ToDoWidget;
    $html = $widget->render($widget->getData());

    expect($html)->toContain('Invoices &amp; renewals');
});

it('still says when there is nothing to do', function () {
    $widget = new ToDoWidget;

    expect($widget->render($widget->getData()))->toContain('All caught up');
});

it('still leaves a completed item off the list', function () {
    $done = todoWithTitle('Already handled');
    $done->update(['status' => 'completed']);

    $widget = new ToDoWidget;

    expect($widget->render($widget->getData()))->not->toContain('Already handled');
});
