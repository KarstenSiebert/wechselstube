<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { assets } from '@/routes';
import babelfees from '@/routes/babelfees';
import contacts from '@/routes/contacts';
import inbounds from '@/routes/inbounds';
import payments from '@/routes/payments';
import history from '@/routes/history';
import mints from '@/routes/mints';
import swaps from '@/routes/swaps';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Contact, Banknote, Coins, HandCoins, History, SwitchCamera, Pickaxe, ServerCog } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';

const mainNavItems: NavItem[] = [
    {
        title: "assets",
        href: assets(),
        icon: Banknote,
    },
    {
        title: "babelfees",
        href: babelfees.index(),
        icon: Coins,
    },
    {
        title: "requests",
        href: payments.index(),
        icon: HandCoins,
    },
    {
        title: "mints",
        href: mints.index(),
        icon: Pickaxe,
    },
    {
        title: "swaps",
        href: swaps.index(),
        icon: SwitchCamera,
    },
    {
        title: "inbounds",
        href: inbounds.index(),
        icon: ServerCog,
    },
    {
        title: "contacts",
        href: contacts.index(),
        icon: Contact,
    },
    {
        title: "history",
        href: history.index(),
        icon: History,
    },
];

const footerNavItems: NavItem[] = [

];

</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="assets()">
                        <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
