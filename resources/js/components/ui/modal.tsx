import React from "react";

interface ModalProps {
    onClose: () => void;
    title?: React.ReactNode;
    children: React.ReactNode;
}

const Modal: React.FC<ModalProps> = ({ onClose, title, children }) => {
    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div className="relative bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
                <button
                    className="absolute top-2 right-2 text-gray-600 hover:text-gray-900"
                    onClick={onClose}
                >
                    &times;
                </button>
                {title ? (
                    <div className="mb-4 pr-8">
                        <h2 className="text-lg font-semibold text-gray-900">{title}</h2>
                    </div>
                ) : null}
                {children}
            </div>
        </div>
    );
};

export default Modal;
