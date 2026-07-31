// Alpine, imported here because nothing else brings it.
//
// This file used to hold a single comment saying Alpine came bundled with
// Livewire 3. Livewire is not installed, so nothing imported it, the built
// bundle was zero bytes, and the four admin screens written against Alpine -
// the invoice builder, both quote builders and the product editor - rendered
// their line items inside a <template x-for> that produced nothing.
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();
