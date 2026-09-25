{{-- A page with a side column (contact, password, new ticket): the side column
     goes under the main one when there is no room beside it. Kept with the
     pages rather than in the client layout, because a theme may bring its own
     layout (flavor does, and so do custom themes) and these pages have to lay
     out the same under any of them. --}}
@once
<style>
    .pn-aside-grid{display:grid;grid-template-columns:1fr var(--aside,360px);gap:32px;align-items:start}
    @media(max-width:900px){.pn-aside-grid{grid-template-columns:minmax(0,1fr)}}
</style>
@endonce
