<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { transfer } from '@/routes/teams';
import type { Team, TeamMember } from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    open: boolean;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const eligibleMembers = computed(() =>
    props.members.filter((member) => member.role !== 'owner'),
);

const selectedUser = ref<string>('');
const formKey = ref(0);

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        selectedUser.value = '';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="transfer.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Transfer ownership</DialogTitle>
                    <DialogDescription>
                        Choose a member to become the new owner of
                        <strong>{{ props.team.name }}</strong
                        >. You'll become an admin.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-2">
                    <Label for="user">New owner</Label>
                    <Select
                        v-model="selectedUser"
                        name="user"
                        data-test="transfer-owner-select"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Select a member" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="member in eligibleMembers"
                                :key="member.id"
                                :value="String(member.id)"
                            >
                                {{ member.name }} ({{ member.email }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.user" />
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="transfer-owner-confirm"
                        variant="destructive"
                        :disabled="processing || !selectedUser"
                    >
                        Transfer ownership
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
