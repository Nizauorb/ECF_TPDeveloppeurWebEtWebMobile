export default class Route {
    constructor(url, title, pathHtml, pathJS = "", headerHtml = "") {
      this.url = url;
      this.title = title;
      this.pathHtml = pathHtml;
      this.pathJS = pathJS;
      this.headerHtml = headerHtml;
    }
}