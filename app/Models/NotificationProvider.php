<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationProvider extends Model
{
    protected $fillable = ["name", "type", "settings", "active"];

    protected function casts(): array
    {
        return [
            "settings" => "array",
            "active" => "boolean",
        ];
    }

    public function rules()
    {
        return $this->hasMany(NotificationRule::class, "provider_id");
    }

    /**
     * What the edit dialog is allowed to know about this provider.
     *
     * The screen used to hand the whole row to JavaScript, which put the bot
     * token and the webhook secret into the page source of every admin who
     * opened the notifications screen. Nothing on that form needs them: a
     * blank secret field is understood as "leave the stored one alone".
     *
     * @return array<string, mixed>
     */
    public function editorPayload(): array
    {
        $settings = $this->settings ?? [];

        foreach (['bot_token', 'secret'] as $secret) {
            unset($settings[$secret]);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'active' => (bool) $this->active,
            'settings' => $settings,
        ];
    }
}
