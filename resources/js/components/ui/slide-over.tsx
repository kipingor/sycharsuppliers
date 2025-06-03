import * as React from "react";
import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
    DialogTitle,
    TransitionChild,
} from "@headlessui/react";
import { X } from "lucide-react";
import { cn } from "@/lib/utils";

function SlideOver({ open, onClose, children }) {
    return (
        <Dialog open={open} onClose={onClose} className="relative z-10">
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-gray-500/75 transition-opacity duration-500 ease-in-out data-closed:opacity-0"
            />
            <div className="fixed inset-0 overflow-hidden">
                <div className="absolute inset-0 overflow-hidden">
                    <div className="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
                        {children}
                    </div>
                </div>
            </div>
        </Dialog>
    );
}

function SlideOverPanel({ className, children }) {
    return (
        <DialogPanel
            transition
            className={cn(
                "pointer-events-auto relative w-screen max-w-md transform transition duration-500 ease-in-out data-closed:translate-x-full sm:duration-700",
                className
            )}
        >
        {children}
        </DialogPanel>
    );
}

function SlideOverHeader({ title, onClose }) {
    return (
        <div className="absolute top-0 left-0 -ml-8 flex w-full items-center pt-4 pr-2 duration-500 ease-in-out data-closed:opacity-0 sm:-ml-10 sm:pr-4">
            <button
                type="button"
                onClick={onClose}
                className="relative rounded-md text-gray-300 hover:text-white focus:ring-2 focus:ring-white focus:outline-hidden"
            >
                <span className="absolute -inset-2.5" />
                <span className="sr-only">Close panel</span>
                <X size={16} />
            </button>
            <DialogTitle className="text-base font-semibold text-gray-900 ml-10 bg-white shadow-sm px-4 py-2 rounded">
                {title}
            </DialogTitle>
        </div>
    );
}

function SlideOverBody({ children }) {
    return (
        <div className="flex h-full flex-col overflow-y-scroll bg-white py-6 shadow-xl">
            <div className="relative mt-6 flex-1 px-4 sm:px-6">{children}</div>
        </div>
    );
}

export { SlideOver, SlideOverPanel, SlideOverHeader, SlideOverBody };
