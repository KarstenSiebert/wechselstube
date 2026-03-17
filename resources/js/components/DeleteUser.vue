<script setup lang="ts">
import { destroy } from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const passwordInput = ref<InstanceType<typeof Input> | null>(null);
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall :title="$t('delete_account')" :description="$t('delete_your_account_and_all_of_its_resources')" />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">{{ $t('warning') }}</p>
                <p class="text-sm">{{ $t('please_proceed_with_caution_this_cannot_be_undone') }}</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">{{ $t('delete_account') }}</Button>
                </DialogTrigger>
                <DialogContent>
                    <Form v-bind="destroy.form()" reset-on-success @error="() => passwordInput?.$el?.focus()" :options="{
                        preserveScroll: true,
                    }" class="space-y-6" v-slot="{ errors, processing, reset, clearErrors }">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>{{ $t('are_you_sure_you_want_to_delete_your_account') }}</DialogTitle>
                            <DialogDescription> {{ $t('once_your_account_is_deleted') }}
                            </DialogDescription>
                        </DialogHeader>

                        <div class="grid gap-2">
                            <Label for="password" class="sr-only">{{ $t('password') }}</Label>
                            <Input id="password" type="password" name="password" ref="passwordInput"
                                :placeholder="$t('password')" />
                            <InputError :message="errors.password" />
                        </div>

                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" @click="
                                    () => {
                                        clearErrors();
                                        reset();
                                    }
                                ">
                                    {{ $t('cancel') }}
                                </Button>
                            </DialogClose>

                            <Button type="submit" variant="destructive" :disabled="processing"> {{ $t('delete_account')
                                }} </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
