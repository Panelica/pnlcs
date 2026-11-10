/*
 * The term grid on the Panelica Licences page.
 *
 * Twelve boxes, one per term of months. Typing a price into any of them fills
 * the others at the same price per month, and the margin under each box
 * follows what is in it.
 *
 * WHY A BOX IS "TOUCHED". The first version filled only the boxes that were
 * empty, so typing 50 filled the others from the 5 the operator had typed so
 * far and then never corrected them: the second keystroke found no empty box
 * left. A box the operator typed into is theirs and is never written to; every
 * other box is recomputed on each keystroke, which is what makes the grid
 * follow the number rather than its first digit. Clearing a box hands it back.
 */
(function (global) {
    'use strict';

    function round2(value) {
        return Math.round(value * 100) / 100;
    }

    /** The margin text and class for a box, or null when it cannot be worked out. */
    function marginOf(box) {
        var cost = parseFloat(box.dataset.cost);
        var value = parseFloat(box.value);

        if (box.value === '' || isNaN(cost) || isNaN(value)) {
            return null;
        }

        var diff = round2(value - cost);

        return { text: (diff >= 0 ? '+' : '') + diff.toFixed(2), ok: diff >= 0 };
    }

    /**
     * Fill every box the operator has not typed into from the one they have,
     * at the same price per month.
     *
     * @param {Array} boxes  every box in the grid
     * @param {object} source  the box that was typed into
     */
    function spread(boxes, source) {
        var value = parseFloat(source.value);
        var months = parseFloat(source.dataset.months);

        if (source.value === '' || isNaN(value) || value <= 0 || !(months > 0)) {
            return;
        }

        var perMonth = value / months;

        boxes.forEach(function (box) {
            if (box === source || box.dataset.touched === '1') {
                return;
            }

            box.value = round2(perMonth * parseFloat(box.dataset.months)).toFixed(2);
        });
    }

    /** Mark the box as the operator's, or hand it back when they cleared it. */
    function touch(box) {
        box.dataset.touched = box.value === '' ? '' : '1';
    }

    function paint(box) {
        var span = box.parentElement && box.parentElement.querySelector('.m');

        if (!span) {
            return;
        }

        var margin = marginOf(box);
        span.textContent = margin ? margin.text : '';
        span.className = margin ? 'm ' + (margin.ok ? 'ok' : 'bad') : 'm';
    }

    function bind(grid) {
        var boxes = Array.prototype.slice.call(grid.querySelectorAll('input[data-months]'));

        // A price already saved is the operator's, not something to overwrite.
        boxes.forEach(function (box) {
            touch(box);
            paint(box);
        });

        boxes.forEach(function (box) {
            box.addEventListener('input', function () {
                touch(box);
                spread(boxes, box);
                boxes.forEach(paint);
            });
        });
    }

    function init(root) {
        Array.prototype.slice.call((root || document).querySelectorAll('.pl-terms')).forEach(bind);
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = { spread: spread, touch: touch, marginOf: marginOf, round2: round2 };
    } else {
        global.pnlcsLicenseTerms = { init: init, spread: spread, touch: touch, marginOf: marginOf };
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () { init(document); });
        } else {
            init(document);
        }
    }
})(typeof window !== 'undefined' ? window : globalThis);
