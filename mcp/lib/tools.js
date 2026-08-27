// Every tool is one PNLCS API action with a typed argument list. Reads are
// always offered; tools that create or change anything exist only when the
// operator opted in with PNLCS_ALLOW_WRITES=1 - an assistant wired up for
// reporting must not even see a suspend button.

const int = (description) => ({ type: 'integer', description });
const str = (description) => ({ type: 'string', description });

const READ_TOOLS = [
  { name: 'get_stats', action: 'getstats', description: 'Business overview: client, order, invoice and revenue totals.', args: {} },
  { name: 'get_health', action: 'gethealthstatus', description: 'Health of the PNLCS install itself.', args: {} },
  { name: 'list_clients', action: 'getclients', description: 'List clients, newest first. Optional text search and paging.', args: { search: str('Match against name, email or company'), status: str('active, inactive or closed'), limitnum: int('Page size, default 25'), limitstart: int('Offset to start from') } },
  { name: 'get_client', action: 'getclientsdetails', description: 'One client with contacts, by clientid or email.', args: { clientid: int('Client id'), email: str('Client email, instead of the id') } },
  { name: 'list_client_services', action: 'getclientsproducts', description: 'Hosting services that belong to one client.', args: { clientid: int('Client id') }, required: ['clientid'] },
  { name: 'list_client_domains', action: 'getclientsdomains', description: 'Domains that belong to one client.', args: { clientid: int('Client id') }, required: ['clientid'] },
  { name: 'list_invoices', action: 'getinvoices', description: 'List invoices. Filter by status (draft, unpaid, paid, overdue, cancelled) or client.', args: { status: str('Invoice status'), userid: int('Only this client'), limitnum: int('Page size, default 25'), limitstart: int('Offset to start from') } },
  { name: 'get_invoice', action: 'getinvoice', description: 'One invoice with its line items.', args: { invoiceid: int('Invoice id') }, required: ['invoiceid'] },
  { name: 'list_orders', action: 'getorders', description: 'List orders. Filter by status (pending, active, fraud, cancelled) or client.', args: { status: str('Order status'), userid: int('Only this client'), limitnum: int('Page size, default 25'), limitstart: int('Offset to start from') } },
  { name: 'list_tickets', action: 'gettickets', description: 'List support tickets. Filter by status.', args: { status: str('Ticket status, e.g. open, answered, closed'), limitnum: int('Page size, default 25'), limitstart: int('Offset to start from') } },
  { name: 'get_ticket', action: 'getticket', description: 'One ticket with its replies and notes.', args: { ticketid: int('Ticket id') }, required: ['ticketid'] },
  { name: 'get_ticket_counts', action: 'getticketcounts', description: 'Ticket totals per status.', args: {} },
  { name: 'list_transactions', action: 'gettransactions', description: 'Payment transactions, newest first.', args: { clientid: int('Only this client'), limitnum: int('Page size, default 25'), limitstart: int('Offset to start from') } },
  { name: 'list_products', action: 'getproducts', description: 'The product catalogue.', args: {} },
  { name: 'get_activity_log', action: 'getactivitylog', description: 'Recent admin and system activity.', args: { limitnum: int('Entries to return, default 25'), limitstart: int('Offset to start from') } },
];

const WRITE_TOOLS = [
  { name: 'add_client', action: 'addclient', method: 'POST', description: 'Create a client. With password2 the client also gets a portal login.', args: { firstname: str('First name'), lastname: str('Last name'), email: str('Email, must be unused'), password2: str('Optional portal password, 8+ characters'), companyname: str('Company'), country: str('Two-letter country code') }, required: ['firstname', 'lastname', 'email'] },
  { name: 'create_invoice', action: 'createinvoice', method: 'POST', description: 'Create an invoice for a client with one or more line items.', args: { userid: int('Client id'), duedate: str('Due date, YYYY-MM-DD'), items: { type: 'array', description: 'Line items', items: { type: 'object', properties: { description: { type: 'string' }, amount: { type: 'number' }, taxed: { type: 'boolean' } }, required: ['description', 'amount'] } } }, required: ['userid', 'items'] },
  { name: 'add_invoice_payment', action: 'addinvoicepayment', method: 'POST', description: 'Record a payment against an invoice. Marks it paid when it covers the balance.', args: { invoiceid: int('Invoice id'), transid: str('Transaction reference'), amount: { type: 'number', description: 'Amount paid' }, gateway: str('Gateway name, defaults to banktransfer') }, required: ['invoiceid', 'transid', 'amount'] },
  { name: 'open_ticket', action: 'openticket', method: 'POST', description: 'Open a support ticket.', args: { deptid: int('Ticket department id'), subject: str('Subject'), message: str('Message body'), email: str('Email of the person the ticket is for'), priority: str('low, medium, high or critical') }, required: ['deptid', 'subject', 'message', 'email'] },
  { name: 'add_ticket_reply', action: 'addticketreply', method: 'POST', description: 'Reply to an existing ticket.', args: { ticketid: int('Ticket id'), message: str('Reply body') }, required: ['ticketid', 'message'] },
  { name: 'suspend_service', action: 'modulesuspend', method: 'POST', description: 'Suspend a hosting service on its server. Ask the operator before using this.', args: { serviceid: int('Service id'), reason: str('Reason shown on the account') }, required: ['serviceid'] },
  { name: 'unsuspend_service', action: 'moduleunsuspend', method: 'POST', description: 'Lift the suspension of a hosting service.', args: { serviceid: int('Service id') }, required: ['serviceid'] },
];

function toToolDescriptor({ name, description, args, required }) {
  return {
    name,
    description,
    inputSchema: { type: 'object', properties: args, required: required ?? [] },
  };
}

export function toolset(allowWrites) {
  return allowWrites ? [...READ_TOOLS, ...WRITE_TOOLS] : READ_TOOLS;
}

export function descriptors(allowWrites) {
  return toolset(allowWrites).map(toToolDescriptor);
}

export function findTool(allowWrites, name) {
  return toolset(allowWrites).find((t) => t.name === name);
}
