<script setup>
import {
    AlertDialogRoot,
    AlertDialogPortal,
    AlertDialogOverlay,
    AlertDialogContent,
    AlertDialogTitle,
    AlertDialogDescription,
} from 'reka-ui';
import { AlertTriangle } from 'lucide-vue-next';
import { Button } from '@/Components/ui/button';
import { useConfirm } from '@/composables/useConfirm';

const { state, handleConfirm, handleCancel } = useConfirm();

const onOpenChange = (isOpen) => {
    if (! isOpen) {
        handleCancel();
    }
};
</script>

<template>
    <AlertDialogRoot :open="state.isOpen" @update:open="onOpenChange">
        <AlertDialogPortal>
            <AlertDialogOverlay
                class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
            />
            <AlertDialogContent
                class="fixed left-1/2 top-1/2 z-50 grid w-full max-w-md -translate-x-1/2 -translate-y-1/2 gap-4 rounded-2xl border bg-card p-6 shadow-lg data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95"
            >
                <div class="flex items-start gap-4">
                    <div
                        v-if="state.variant === 'destructive'"
                        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-destructive/10 text-destructive"
                    >
                        <AlertTriangle class="size-5" />
                    </div>
                    <div class="flex flex-col gap-2">
                        <AlertDialogTitle class="text-lg font-semibold text-foreground">
                            {{ state.title }}
                        </AlertDialogTitle>
                        <AlertDialogDescription
                            v-if="state.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ state.description }}
                        </AlertDialogDescription>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button variant="outline" @click="handleCancel">
                        {{ state.cancelText }}
                    </Button>
                    <Button
                        :variant="state.variant === 'destructive' ? 'destructive' : 'default'"
                        @click="handleConfirm"
                    >
                        {{ state.confirmText }}
                    </Button>
                </div>
            </AlertDialogContent>
        </AlertDialogPortal>
    </AlertDialogRoot>
</template>
