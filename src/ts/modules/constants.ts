interface Constants {
  ANIMATION_DURATION: number;
  ANIMATION_DELAY: number;
  COOKIE_NAME: string;
  COOKIE_EXPIRY_DAYS: number;
  TEMPLATES: {
    ADD_RATE: string;
    DELETE_RATE: string;
  };
}

export const CONSTANTS: Constants = {
  ANIMATION_DURATION: 300,
  ANIMATION_DELAY: 10,
  COOKIE_NAME: "wcsdm_active_tab",
  COOKIE_EXPIRY_DAYS: 30,
  TEMPLATES: {
    ADD_RATE: "wcsdm-modal-rate-add",
    DELETE_RATE: "wcsdm-modal-rate-delete",
  },
};