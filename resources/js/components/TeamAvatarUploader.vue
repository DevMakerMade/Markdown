<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import {
    destroy as destroyAvatar,
    update as updateAvatar,
} from '@/routes/teams/avatar';
import type { Team } from '@/types';

type Props = {
    team: Team;
};

const props = defineProps<Props>();

const { getInitials } = useInitials();

const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);

const form = useForm<{ avatar: File | null }>({ avatar: null });

const displayedAvatar = computed(
    () => previewUrl.value ?? props.team.avatarUrl,
);

function selectFile() {
    fileInput.value?.click();
}

function onFileChange(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (!file) {
        return;
    }

    form.avatar = file;
    previewUrl.value = URL.createObjectURL(file);

    form.post(updateAvatar(props.team.slug).url, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('avatar');
            clearPreview();
        },
    });
}

function clearPreview() {
    if (previewUrl.value) {
        URL.revokeObjectURL(previewUrl.value);
        previewUrl.value = null;
    }
}

function removeAvatar() {
    router.delete(destroyAvatar(props.team.slug).url, {
        preserveScroll: true,
        onSuccess: () => clearPreview(),
    });
}
</script>

<template>
    <div class="flex items-center gap-4">
        <Avatar :key="displayedAvatar ?? 'fallback'" class="h-16 w-16">
            <AvatarImage
                v-if="displayedAvatar"
                :src="displayedAvatar"
                :alt="props.team.name"
            />
            <AvatarFallback class="text-lg">{{
                getInitials(props.team.name)
            }}</AvatarFallback>
        </Avatar>

        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    name="avatar"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    data-test="team-avatar-input"
                    @change="onFileChange"
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="team-avatar-upload"
                    :disabled="form.processing"
                    @click="selectFile"
                >
                    Upload image
                </Button>
                <Button
                    v-if="props.team.avatarUrl"
                    type="button"
                    variant="ghost"
                    size="sm"
                    data-test="team-avatar-remove"
                    @click="removeAvatar"
                >
                    Remove
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                PNG, JPG, or WEBP up to 2 MB.
            </p>
            <InputError :message="form.errors.avatar" />
        </div>
    </div>
</template>
