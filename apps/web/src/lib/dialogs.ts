import Swal from "sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css";

const danger = "#b42318";
const teal = "#0f6b6b";

export async function confirmDelete(name: string): Promise<boolean> {
  const result = await Swal.fire({
    title: `Delete ${name}?`,
    text: "This cannot be undone.",
    icon: "warning",
    showCancelButton: true,
    focusCancel: true,
    confirmButtonText: "Delete",
    cancelButtonText: "Cancel",
    confirmButtonColor: danger,
    reverseButtons: true,
  });
  return result.isConfirmed;
}

export function showWarning(message: string): Promise<void> {
  return Swal.fire({
    title: "Cannot delete",
    text: message,
    icon: "warning",
    confirmButtonText: "OK",
    confirmButtonColor: teal,
  }).then(() => undefined);
}
