<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { decline } from '@/routes/invitations';
import { index as teamsIndex } from '@/routes/teams';

type Props = {
    invitation: {
        code: string;
        email: string;
        teamName: string;
    };
};

const props = defineProps<Props>();

const processing = ref(false);

const declineInvitation = () => {
    router.visit(decline(props.invitation.code), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
};
</script>

<template>
    <Head title="Decline invitation" />

    <div class="flex flex-1 items-center justify-center p-6">
        <Card class="w-full max-w-md">
            <CardHeader>
                <CardTitle>Decline invitation</CardTitle>
                <CardDescription>
                    You were invited to join
                    <strong>{{ props.invitation.teamName }}</strong
                    >. Declining will remove this invitation.
                </CardDescription>
            </CardHeader>

            <CardContent class="text-sm text-muted-foreground">
                Invitation sent to {{ props.invitation.email }}.
            </CardContent>

            <CardFooter class="gap-2">
                <Button variant="secondary" as-child>
                    <Link :href="teamsIndex()">Cancel</Link>
                </Button>
                <Button
                    data-test="decline-invitation-confirm"
                    variant="destructive"
                    :disabled="processing"
                    @click="declineInvitation"
                >
                    Decline invitation
                </Button>
            </CardFooter>
        </Card>
    </div>
</template>
