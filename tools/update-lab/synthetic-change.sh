#!/usr/bin/env bash
# The changes the lab's "next release" makes, applied to a copy of the code.
# They are chosen to meet the operator's changes made by customise.php:
#   public/robots.txt          - the same lines as the operator: a conflict
#   admin layout (bottom)      - the operator edits its top: a clean merge
#   sections/footer view       - the lab theme overrides it: a warning
#   app/Hooks/example.php.disabled removed, app/Support/LabProbe.php added
#   a migration creating lab_probe; an English string changed
#
#   synthetic-change.sh <dir> good|bad-migration|bad-view
set -euo pipefail
DIR="$1"; KIND="${2:-good}"
cd "$DIR"

python3 - <<'PY'
import re
p = 'public/robots.txt'
s = open(p).read()
s = s.replace('User-agent: *', 'User-agent: *\n# lab release: a line the new version adds where the operator also writes', 1)
open(p, 'w').write(s)

p = 'resources/views/admin/layouts/app.blade.php'
s = open(p).read()
i = s.rindex('</body>')
s = s[:i] + '{{-- lab release: added near the end of the layout --}}\n' + s[i:]
open(p, 'w').write(s)

p = 'resources/views/sections/footer.blade.php'
s = open(p).read()
open(p, 'w').write('{{-- lab release: footer changed --}}\n' + s)

p = 'lang/en/common.php'
s = open(p).read()
s = s.replace("return [", "return [\n    'lab_probe' => 'Lab release string',", 1)
open(p, 'w').write(s)
PY

rm -f app/Hooks/example.php.disabled
mkdir -p app/Support
cat > app/Support/LabProbe.php <<'PHP'
<?php

namespace App\Support;

/** Added by the update lab's synthetic release. */
class LabProbe
{
    public const RELEASE = 'lab';
}
PHP

MIGRATION=database/migrations/2099_01_01_000000_create_lab_probe_table.php
if [ "$KIND" = bad-migration ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Creates a table, then fails: the rollback must drop it again.
        Schema::create('lab_probe', function (Blueprint $t) {
            $t->id();
        });
        throw new RuntimeException('lab: this migration fails on purpose');
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_probe');
    }
};
PHP
else
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_probe', function (Blueprint $t) {
            $t->id();
            $t->string('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_probe');
    }
};
PHP
fi

if [ "$KIND" = bad-view ]; then
    # Renders fine at compile time, throws when a visitor opens the admin login:
    # only the health check after the update can catch it.
    sed -i '1i @php throw new \\RuntimeException("lab: this view fails on purpose"); @endphp' resources/views/admin/auth/login.blade.php
fi
