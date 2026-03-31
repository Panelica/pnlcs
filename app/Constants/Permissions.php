<?php

namespace App\Constants;

class Permissions
{
    const LIST_CLIENTS = 'list_clients';
    const VIEW_CLIENTS = 'view_clients';
    const CREATE_CLIENTS = 'create_clients';
    const EDIT_CLIENTS = 'edit_clients';
    const DELETE_CLIENTS = 'delete_clients';
    const LIST_ORDERS = 'list_orders';
    const VIEW_ORDERS = 'view_orders';
    const MANAGE_ORDERS = 'manage_orders';
    const LIST_INVOICES = 'list_invoices';
    const VIEW_INVOICES = 'view_invoices';
    const CREATE_INVOICES = 'create_invoices';
    const MANAGE_INVOICES = 'manage_invoices';
    const LIST_PRODUCTS = 'list_products';
    const MANAGE_PRODUCTS = 'manage_products';
    const LIST_SERVICES = 'list_services';
    const VIEW_SERVICES = 'view_services';
    const MANAGE_SERVICES = 'manage_services';
    const LIST_DOMAINS = 'list_domains';
    const MANAGE_DOMAINS = 'manage_domains';
    const LIST_TICKETS = 'list_tickets';
    const VIEW_TICKETS = 'view_tickets';
    const REPLY_TICKETS = 'reply_tickets';
    const MANAGE_TICKETS = 'manage_tickets';
    const MANAGE_STAFF = 'manage_staff';
    const MANAGE_ROLES = 'manage_roles';
    const MANAGE_CURRENCIES = 'manage_currencies';
    const MANAGE_TAX = 'manage_tax';
    const MANAGE_SERVERS = 'manage_servers';
    const MANAGE_GATEWAYS = 'manage_gateways';
    const MANAGE_REGISTRARS = 'manage_registrars';
    const MANAGE_PROMOTIONS = 'manage_promotions';
    const MANAGE_EMAIL_TEMPLATES = 'manage_email_templates';
    const MANAGE_TICKET_CONFIG = 'manage_ticket_config';
    const MANAGE_ANNOUNCEMENTS = 'manage_announcements';
    const MANAGE_KB = 'manage_kb';
    const MANAGE_SECURITY = 'manage_security';
    const MANAGE_AFFILIATES = 'manage_affiliates';
    const VIEW_REPORTS = 'view_reports';
    const MANAGE_SETTINGS = 'manage_settings';
    const VIEW_ACTIVITY_LOG = 'view_activity_log';
    const VIEW_SYSTEM = 'view_system';
    const LIST_QUOTES = 'list_quotes';
    const MANAGE_QUOTES = 'manage_quotes';
    const LIST_PROJECTS = 'list_projects';
    const MANAGE_PROJECTS = 'manage_projects';

    public static function grouped(): array
    {
        return [
            'Clients' => [self::LIST_CLIENTS, self::VIEW_CLIENTS, self::CREATE_CLIENTS, self::EDIT_CLIENTS, self::DELETE_CLIENTS],
            'Orders' => [self::LIST_ORDERS, self::VIEW_ORDERS, self::MANAGE_ORDERS],
            'Invoices' => [self::LIST_INVOICES, self::VIEW_INVOICES, self::CREATE_INVOICES, self::MANAGE_INVOICES],
            'Products' => [self::LIST_PRODUCTS, self::MANAGE_PRODUCTS],
            'Services' => [self::LIST_SERVICES, self::VIEW_SERVICES, self::MANAGE_SERVICES],
            'Domains' => [self::LIST_DOMAINS, self::MANAGE_DOMAINS],
            'Tickets' => [self::LIST_TICKETS, self::VIEW_TICKETS, self::REPLY_TICKETS, self::MANAGE_TICKETS],
            'Configuration' => [self::MANAGE_STAFF, self::MANAGE_ROLES, self::MANAGE_CURRENCIES, self::MANAGE_TAX, self::MANAGE_SERVERS, self::MANAGE_GATEWAYS, self::MANAGE_REGISTRARS, self::MANAGE_PROMOTIONS, self::MANAGE_EMAIL_TEMPLATES, self::MANAGE_TICKET_CONFIG, self::MANAGE_ANNOUNCEMENTS, self::MANAGE_KB, self::MANAGE_SECURITY, self::MANAGE_AFFILIATES],
            'Reports & System' => [self::VIEW_REPORTS, self::MANAGE_SETTINGS, self::VIEW_ACTIVITY_LOG, self::VIEW_SYSTEM],
            'Quotes & Projects' => [self::LIST_QUOTES, self::MANAGE_QUOTES, self::LIST_PROJECTS, self::MANAGE_PROJECTS],
        ];
    }

    public static function all(): array
    {
        return array_merge(...array_values(static::grouped()));
    }
}
