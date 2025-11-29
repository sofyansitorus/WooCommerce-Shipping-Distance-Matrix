type TemplateFunction = (templateData: object) => string;
type TemplateSelector = (templateName: string) => TemplateFunction;

declare const wp: {
	template: TemplateSelector;
};
