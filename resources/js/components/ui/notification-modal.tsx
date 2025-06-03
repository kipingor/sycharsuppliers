import * as React from "react";
import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    DialogTitle,
} from "@headlessui/react";
import { cn } from "@/lib/utils";

function Modal({ open, onClose, children, className }) {
    return (
        <Dialog open={open} onClose={onClose} className="relative z-10">
            <DialogBackdrop
                className="fixed inset-0 bg-gray-500/75 transition-opacity data-closed:opacity-0 data-enter:duration-300 data-enter:ease-out data-leave:duration-200 data-leave:ease-in"
            />
            <div className="fixed inset-0 z-10 w-screen overflow-y-auto">
                <div className="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                    <DialogPanel
                        className={cn(
                        "relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all data-closed:translate-y-4 data-closed:opacity-0 data-enter:duration-300 data-enter:ease-out data-leave:duration-200 data-leave:ease-in sm:my-8 sm:w-full sm:max-w-lg data-closed:sm:translate-y-0 data-closed:sm:scale-95",
                        className
                        )}
                    >
                        {children}
                    </DialogPanel>
                </div>
            </div>
        </Dialog>
    );
}

function ModalHeader({ title, icon }) {
    return (
        <div className="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
            <div className="sm:flex sm:items-start">
                {icon && (
                    <div className="mx-auto flex size-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:size-10">
                        {icon}
                    </div>
                )}
                <div className="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                    <DialogTitle as="h3" className="text-base font-semibold text-gray-900">
                        {title}
                    </DialogTitle>
                </div>
            </div>
        </div>
    );
}

function ModalBody({ children }) {
    return <div className="px-4 py-2 text-sm text-gray-500">{children}</div>;
}

function ModalFooter({ onClose, onConfirm, confirmText = "Confirm", cancelText = "Cancel" }) {
    return (
        <div className="bg-gray-50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <button
                type="button"
                onClick={onConfirm}
                className="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-xs hover:bg-red-500 sm:ml-3 sm:w-auto"
            >
                {confirmText}
            </button>
            <button
                type="button"
                onClick={onClose}
                className="mt-3 inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 ring-1 shadow-xs ring-gray-300 ring-inset hover:bg-gray-50 sm:mt-0 sm:w-auto"
            >
                {cancelText}
            </button>
        </div>
    );
}

export { Modal, ModalHeader, ModalBody, ModalFooter };
