<script setup lang="ts">
import { store } from '@/actions/App/Http/Controllers/Auth/EmailVerificationNotificationController';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout :title="$t('verify_email')" :description="$t('please_verify_your_email_address_by_clicking')">

        <Head :title="$t('email_verification')" />

        <div v-if="status === 'verification-link-sent'" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ $t('a_new_verification_link_has_been_sent') }}
        </div>

        <Form v-bind="store.form()" class="space-y-6 text-center" v-slot="{ processing }">
            <Button :disabled="processing" variant="secondary">
                <LoaderCircle v-if="processing" class="h-4 w-4 animate-spin" />
                {{ $t('"resend_verification_email') }}
            </Button>

            <TextLink :href="logout()" as="button" class="mx-auto block text-sm"> {{ $t('logout_l') }} </TextLink>
        </Form>
    </AuthLayout>
</template>
