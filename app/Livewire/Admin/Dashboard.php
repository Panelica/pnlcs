<?php

namespace App\Livewire\Admin;

use App\Models\Client;
use App\Models\Admin;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        return view('livewire.admin.dashboard', [
            'totalClients' => Client::count(),
            'activeClients' => Client::where('status', 'active')->count(),
            'totalAdmins' => Admin::count(),
        ])->layout('components.layouts.admin');
    }
}
